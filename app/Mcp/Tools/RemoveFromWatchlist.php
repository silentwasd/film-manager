<?php

namespace App\Mcp\Tools;

use App\Models\FilmWatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('remove_from_watchlist')]
#[Title('Убрать фильм из списка')]
#[Description(
    'Удаляет фильм из списка просмотра пользователя вместе со статусом. '.
    'Отзывы и заметки при этом остаются. Действие необратимо — спросить пользователя.'
)]
#[IsDestructive]
#[IsIdempotent]
class RemoveFromWatchlist extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'film_id' => 'required|integer|min:1',
        ]);

        $deleted = FilmWatcher::query()
            ->where('watcher_id', $request->user()->getAuthIdentifier())
            ->where('film_id', $data['film_id'])
            ->delete();

        return Response::json([
            'removed' => $deleted > 0,
            'film_id' => $data['film_id'],
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'film_id' => $schema->integer()
                ->description('Идентификатор фильма.')
                ->required(),
        ];
    }
}
