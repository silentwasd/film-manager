<?php

namespace App\Http\Controllers\Management;

use App\Enums\FilmFormat;
use App\Enums\PersonRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Management\FilmResource;
use App\Services\Kinopoisk\KinopoiskImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Импорт с Кинопоиска для расширения браузера (`/workspace/film-manager/kinopoisk-extension`).
 * Данные снимает расширение со страниц, сервер только раскладывает их по справочникам.
 */
class KinopoiskImportController extends Controller
{
    public function __construct(
        protected KinopoiskImporter $importer
    )
    {
    }

    /**
     * Есть ли уже карточка и кого из людей расширению надо добрать
     * со страниц персон (оригинальное имя, дата рождения, фото).
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'kinopoisk_id'           => 'required|integer|min:1',
            'name'                   => 'nullable|string|max:255',
            'original_name'          => 'nullable|string|max:255',
            'produced_year'          => 'nullable|integer|min:1800|max:2100',
            'people'                 => 'nullable|array|max:200',
            'people.*.kinopoisk_id'  => 'required|integer|min:1',
            'people.*.name'          => 'required|string|max:255',
            'people.*.original_name' => 'nullable|string|max:255',
        ]);

        $result = $this->importer->lookup(
            $data['kinopoisk_id'],
            $data['name'] ?? null,
            $data['original_name'] ?? null,
            $data['produced_year'] ?? null,
            $data['people'] ?? []
        );

        $brief = fn($film) => [
            'id'            => $film->id,
            'name'          => $film->name,
            'original_name' => $film->original_name,
            'produced_year' => $film->produced_year,
        ];

        return response()->json([
            'film'           => $result['film'] ? $brief($result['film']) : null,
            'similar'        => array_map($brief, $result['similar']),
            'unknown_people' => $result['unknown_people'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kinopoisk_id'           => 'required|integer|min:1',
            'name'                   => 'required|string|max:255',
            'original_name'          => 'nullable|string|max:255',
            'format'                 => ['required', Rule::enum(FilmFormat::class)],
            'produced_year'          => 'nullable|integer|min:1800|max:2100',
            'release_date'           => 'nullable|date',
            'description'            => 'nullable|string|max:65536',
            'poster_url'             => 'nullable|url|max:2048',
            'genres'                 => 'nullable|array|max:30',
            'genres.*'               => 'string|max:100',
            'countries'              => 'nullable|array|max:30',
            'countries.*'            => 'string|max:100',
            'companies'              => 'nullable|array|max:10',
            'companies.*'            => 'string|max:255',
            'people'                 => 'nullable|array|max:200',
            'people.*.kinopoisk_id'  => 'required|integer|min:1',
            'people.*.role'          => ['required', Rule::enum(PersonRole::class)],
            // Озвучка мультсериалов — десятки ролей через запятую; длинное
            // не отвергаем, импортёр сам обрежет до размера колонки.
            'people.*.role_details'  => 'nullable|string|max:10000',
            'people.*.name'          => 'required|string|max:255',
            'people.*.original_name' => 'nullable|string|max:255',
            'people.*.birth_date'    => 'nullable|date',
            'people.*.death_date'    => 'nullable|date',
            'people.*.sex'           => 'nullable|in:male,female',
            'people.*.photo_url'     => 'nullable|url|max:2048',
        ]);

        // Постер и фото новых людей качаются синхронно — дольше 30 с FPM по умолчанию.
        set_time_limit(110);

        $result = $this->importer->import($data, $request->user());

        $film = $result['film']->load(['genres', 'countries', 'companies', 'tags']);

        return (new FilmResource($film))
            ->additional(['created' => $result['created'], 'log' => $result['log']])
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }
}
