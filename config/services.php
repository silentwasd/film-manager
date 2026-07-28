<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'transmission' => [
        'downloads' => env('TRANSMISSION_DOWNLOADS'),
    ],

    'shikimori' => [
        // Канонический домен shikimori.one отдаёт 301 на .io; в РФ веб-страницы
        // .one закрыты по 451, поэтому по умолчанию ходим сразу на .io.
        'base_url'   => env('SHIKIMORI_BASE_URL', 'https://shikimori.io'),
        // API требует осмысленный User-Agent, без него отвечает отказом.
        'user_agent' => env('SHIKIMORI_USER_AGENT', 'FilmManager'),
        'timeout'    => env('SHIKIMORI_TIMEOUT', 30),
        // Лимит API — 5rps/90rpm; 250 мс между запросами укладываются с запасом.
        'delay_ms'   => env('SHIKIMORI_DELAY_MS', 250),
    ],

    'kotonet' => [
        'client_id' => env('KOTONET_CLIENT_ID'),
        'client_secret' => env('KOTONET_CLIENT_SECRET'),
        'redirect' => env('KOTONET_REDIRECT_URI'),
        'base_url' => env('KOTONET_BASE_URL'),
    ],

];
