<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Resources\Management\FilmResource;
use App\Services\Shikimori\ShikimoriImporter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class ShikimoriImportController extends Controller
{
    public function __construct(
        protected ShikimoriImporter $importer
    )
    {
    }

    /**
     * Создаёт карточку по ID аниме на Шикимори.
     *
     * Запрос синхронный и не быстрый: с озвучкой это десятки обращений к API
     * с обязательной паузой между ними, плюс выкачивание постера и фото персон.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'shikimori_id' => 'required|integer|min:1',
            'with_people'  => 'nullable|boolean',
            'with_seyu'    => 'nullable|boolean'
        ]);

        // max_execution_time у FPM — 30 с, а импорт с озвучкой идёт дольше:
        // это десятки запросов к API с паузами плюс выкачивание картинок.
        // Поднимаем только для этого запроса, чтобы не трогать php.ini.
        // Потолок сверху — fastcgi_read_timeout nginx (сейчас 120 с).
        set_time_limit(110);

        try {
            $result = $this->importer->import(
                $data['shikimori_id'],
                $request->user(),
                $data['with_people'] ?? true,
                $data['with_seyu'] ?? true
            );
        } catch (ConnectionException|RequestException $exception) {
            abort(502, 'Шикимори не ответил: ' . $exception->getMessage());
        }

        if (!$result['film'])
            abort(404, "Аниме {$data['shikimori_id']} на Шикимори не найдено.");

        $film = $result['film']->load([
            'genres', 'countries', 'companies', 'people', 'people.person'
        ]);

        return (new FilmResource($film))->additional(['log' => $result['log']]);
    }
}
