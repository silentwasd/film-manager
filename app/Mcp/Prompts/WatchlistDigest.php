<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('watchlist_digest')]
#[Title('Разбор списка просмотра')]
#[Description('Собирает картину вкусов по просмотренному: любимые жанры, страны, режиссёры, и что из этого следует.')]
class WatchlistDigest extends Prompt
{
    public function handle(Request $request): Response
    {
        $status = $request->get('status') ?: 'watched';

        return Response::text(trim(<<<TEXT
        Разбери мой список просмотра со статусом «{$status}».

        1. Пройди get_watchlist постранично, пока не соберёшь весь список
           с этим статусом — ориентируйся на поля total и pages.
        2. По карточкам из get_film собери статистику: жанры, страны, годы,
           повторяющиеся режиссёры и студии. Считай по тому, что реально
           вернули инструменты, а не по своим представлениям об этих фильмах.
        3. Опиши вкусы: что явно любимое, что попадается редко, есть ли
           заметные пробелы.
        4. Предложи 5 фильмов из каталога, которых ещё нет в моём списке,
           через search_films. Для каждого объясни, из какой закономерности
           он следует.
        5. Ничего не сохраняй без моего слова.
        TEXT));
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'status',
                description: 'Какой срез разбирать: watched, to-watch, must-finish, dropped. По умолчанию watched.',
                required: false,
            ),
        ];
    }
}
