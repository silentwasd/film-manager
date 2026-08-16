<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Presenter;
use App\Models\FilmPerson;
use App\Models\Person;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_person')]
#[Title('Карточка человека')]
#[Description('Человек каталога по id: даты жизни, страна, фото и фильмография с ролями.')]
#[IsReadOnly]
#[IsIdempotent]
class GetPerson extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'id' => 'required|integer|min:1',
            'role' => 'nullable|string|max:64',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $person = Person::query()->with('country')->find($data['id']);

        if ($person === null) {
            return Response::error("Человека с id {$data['id']} в каталоге нет.");
        }

        $films = $person->films()
            ->with('film')
            ->when($data['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->get()
            ->sortByDesc(fn (FilmPerson $filmPerson) => $filmPerson->film?->produced_year ?? 0)
            ->take($data['limit'] ?? 60)
            ->map(fn (FilmPerson $filmPerson) => array_filter([
                ...($filmPerson->film ? Presenter::filmCard($filmPerson->film) : ['id' => $filmPerson->film_id]),
                'role' => $filmPerson->role?->value,
                'as' => $filmPerson->role_details,
            ], fn ($value) => $value !== null))
            ->values()
            ->all();

        return Response::json([
            ...Presenter::personDetail($person),
            'films_total' => $person->films()->count(),
            'films' => $films,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Идентификатор человека из выдачи search_people.')
                ->required(),
            'role' => $schema->string()
                ->description('Оставить в фильмографии только эту роль: director, actor, voice-actor, producer, screenwriter, composer и т. д.'),
            'limit' => $schema->integer()->description('Сколько работ вернуть, до 200. По умолчанию 60, начиная со свежих.'),
        ];
    }
}
