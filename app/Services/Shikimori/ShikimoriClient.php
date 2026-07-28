<?php

namespace App\Services\Shikimori;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Тонкая обёртка над публичным REST API Шикимори (v1).
 *
 * Авторизация не нужна — всё, что читает импортёр, отдаётся анонимно.
 * Обязателен только User-Agent, без него API отвечает отказом.
 */
class ShikimoriClient
{
    /**
     * Сколько запросов шлём одновременно. Лимит API — 5rps, поэтому пачка
     * из пяти с паузой в секунду ложится ровно в него.
     */
    protected const int PARALLEL = 5;

    public function __construct(
        protected string $baseUrl,
        protected string $userAgent,
        protected int    $timeout,
        protected int    $delayMs
    )
    {
    }

    public function anime(int $id): ?array
    {
        return $this->get("/api/animes/{$id}");
    }

    /**
     * Персонал и персонажи одним ответом.
     * Сэйю здесь НЕ приходят — только у персонажа, см. character().
     */
    public function roles(int $id): array
    {
        return $this->get("/api/animes/{$id}/roles") ?? [];
    }

    public function character(int $id): ?array
    {
        return $this->get("/api/characters/{$id}");
    }

    /**
     * Пачка персонажей за раз. У сэйю нет группового эндпоинта, а поштучно
     * полсотни персонажей — это минуты ожидания, поэтому шлём параллельно
     * по PARALLEL запросов с секундной паузой между пачками: ровно 5rps.
     *
     * @param array<int> $ids
     * @return array<int, array> ключ — id персонажа
     */
    public function characters(array $ids): array
    {
        $result = [];

        foreach (array_chunk($ids, self::PARALLEL) as $index => $chunk) {
            if ($index > 0)
                usleep(1_000_000);

            $responses = Http::pool(fn(Pool $pool) => array_map(
                fn(int $id) => $pool->as((string)$id)
                                    ->withUserAgent($this->userAgent)
                                    ->acceptJson()
                                    ->timeout($this->timeout)
                                    ->get($this->baseUrl . "/api/characters/{$id}"),
                $chunk
            ));

            foreach ($chunk as $id) {
                $response = $responses[(string)$id] ?? null;

                if ($response instanceof Response && $response->successful())
                    $result[$id] = $response->json();
            }
        }

        return $result;
    }

    /**
     * Пачка файлов за раз. Статика лимитом /api/ не ограничена, поэтому
     * без пауз между пачками.
     *
     * @param array<string, string> $paths ключ вызывающего => путь к файлу
     * @return array<string, string> ключ вызывающего => бинарь (неудачные выпадают)
     */
    public function downloadMany(array $paths): array
    {
        $result = [];

        foreach (array_chunk($paths, self::PARALLEL, true) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                $requests = [];

                foreach ($chunk as $key => $path) {
                    $requests[] = $pool->as($key)
                                       ->withUserAgent($this->userAgent)
                                       ->timeout($this->timeout)
                                       ->get(str_starts_with($path, 'http') ? $path : $this->baseUrl . $path);
                }

                return $requests;
            });

            foreach (array_keys($chunk) as $key) {
                $response = $responses[$key] ?? null;

                if ($response instanceof Response && $response->successful())
                    $result[$key] = $response->body();
            }
        }

        return $result;
    }

    public function screenshots(int $id): array
    {
        return $this->get("/api/animes/{$id}/screenshots") ?? [];
    }

    /**
     * Скачивает файл по пути вида /system/animes/original/39790.jpg.
     * Возвращает бинарь либо null, если файл недоступен.
     *
     * Картинка — не повод ронять импорт: на карточке с озвучкой их полсотни,
     * и один отвалившийся по таймауту постер не должен стоить всей карточки.
     * Поэтому сетевые ошибки здесь гасим и отдаём null.
     */
    public function download(string $path): ?string
    {
        $url = str_starts_with($path, 'http') ? $path : $this->baseUrl . $path;

        // Файлы лежат в /system/ и раздаются как статика — под лимит /api/
        // они не попадают, поэтому паузу здесь не выдерживаем: с throttle
        // выкачивание удваивало бы время импорта.
        try {
            $response = Http::withUserAgent($this->userAgent)
                            ->timeout($this->timeout)
                            ->retry(2, 1000, throw: false)
                            ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    protected function get(string $path): array|null
    {
        $this->throttle();

        $response = $this->request()->get($this->baseUrl . $path);

        if ($response->status() === 404)
            return null;

        $response->throw();

        return $response->json();
    }

    protected function request(): PendingRequest
    {
        return Http::withUserAgent($this->userAgent)
                   ->acceptJson()
                   ->timeout($this->timeout)
                   ->retry(3, 2000, throw: false);
    }

    /**
     * У API лимит 5rps / 90rpm. Импорт одного тайтла укладывается
     * в пару десятков запросов, поэтому хватает простой паузы между ними.
     */
    protected function throttle(): void
    {
        if ($this->delayMs > 0)
            usleep($this->delayMs * 1000);
    }
}
