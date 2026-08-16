<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('what_to_watch')]
#[Title('Что посмотреть сегодня')]
#[Description('Подбирает несколько вариантов из списка «хочу посмотреть» с учётом настроения и свободного времени.')]
class WhatToWatch extends Prompt
{
    public function handle(Request $request): Response
    {
        $mood = $request->get('mood') ?: 'без особых предпочтений';
        $time = $request->get('time');

        return Response::text(trim(<<<TEXT
        Подбери мне, что посмотреть сегодня вечером.

        Настроение: {$mood}.
        {$this->timeLine($time)}
        Действуй так:
        1. Возьми get_watchlist со статусом to-watch, при необходимости пролистай страницы.
        2. Если подходящего мало, добавь варианты из must-finish и из search_films по каталогу.
        3. Отбери 3-5 вариантов, для каждого вызови get_film и коротко скажи,
           почему он подходит под настроение. Ничего не выдумывай сверх карточки.
        4. Спроси, какой выбрать, и только после ответа поставь ему статус.
        TEXT));
    }

    private function timeLine(?string $time): string
    {
        return $time ? "Свободного времени: {$time}.\n" : '';
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'mood',
                description: 'Настроение или пожелание: «что-нибудь лёгкое», «страшное», «подумать».',
                required: false,
            ),
            new Argument(
                name: 'time',
                description: 'Сколько есть времени, например «полтора часа» или «весь вечер».',
                required: false,
            ),
        ];
    }
}
