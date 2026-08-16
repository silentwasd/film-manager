<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\WhatToWatch;
use App\Mcp\Prompts\WatchlistDigest;
use App\Mcp\Tools\AddFilmNote;
use App\Mcp\Tools\DeleteCollection;
use App\Mcp\Tools\DeleteFilmNote;
use App\Mcp\Tools\Fetch;
use App\Mcp\Tools\GetCollection;
use App\Mcp\Tools\GetFilm;
use App\Mcp\Tools\GetPerson;
use App\Mcp\Tools\GetWatchlist;
use App\Mcp\Tools\ListCollections;
use App\Mcp\Tools\ListFacets;
use App\Mcp\Tools\ManageCollectionFilms;
use App\Mcp\Tools\RemoveFromWatchlist;
use App\Mcp\Tools\SaveCollection;
use App\Mcp\Tools\Search;
use App\Mcp\Tools\SearchFilms;
use App\Mcp\Tools\SearchPeople;
use App\Mcp\Tools\SetFilmReaction;
use App\Mcp\Tools\SetWatchStatus;
use App\Mcp\Tools\WhoAmI;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Кинокот')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
Каталог фильмов, сериалов, мультфильмов и аниме с личным списком просмотра.
Подключён к аккаунту конкретного пользователя — whoami показывает, к чьему.

Как искать. search_films — основной инструмент: фильтрует по жанрам, странам,
тегам, участникам, студии, году и формату. Жанры, страны и теги задаются
названиями на русском; если непонятно, как значение называется в каталоге,
сначала спросить list_facets. Названия фильмов в базе русские, оригинальные
названия тоже ищутся. Подробности фильма — get_film, людей — search_people
и get_person. search и fetch дают общий поиск по всему сразу.

Каталог небольшой и авторский: в нём около тысячи фильмов, отобранных
пользователем, а не весь мировой прокат. Если фильма в выдаче нет, это значит,
что его нет в каталоге, — не выдумывать записи и не подставлять данные
из своей памяти вместо ответа инструмента. Идентификаторы брать только
из ответов инструментов.

Личные данные. get_watchlist показывает, что пользователь смотрел, бросил или
собирается посмотреть; статус меняется через set_watch_status. Оценка фильма —
set_film_reaction (нравится, не нравится, снять), она же кладёт публичный отзыв,
видимый всем на сайте. add_film_note пишет приватную заметку. Подборки —
list_collections, get_collection, save_collection, manage_collection_films.

Перед любой записью — set_watch_status, set_film_reaction, add_film_note,
save_collection, manage_collection_films, удаления — сказать пользователю,
что именно будет сохранено, и дождаться согласия. Особенно это касается
публичного отзыва и публикации подборки: их увидят посторонние.
TEXT)]
class FilmManagerServer extends Server
{
    /**
     * Дефолтные 15 разрезали бы список инструментов на две страницы, и клиент,
     * не пролиставший nextCursor, не увидел бы половину подборок.
     */
    public int $defaultPaginationLength = 50;

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        WhoAmI::class,

        // Каталог
        SearchFilms::class,
        GetFilm::class,
        SearchPeople::class,
        GetPerson::class,
        ListFacets::class,

        // Обязательная пара для коннекторов ChatGPT, см. App\Mcp\Tools\Search
        Search::class,
        Fetch::class,

        // Список просмотра
        GetWatchlist::class,
        SetWatchStatus::class,
        RemoveFromWatchlist::class,

        // Оценки и заметки
        SetFilmReaction::class,
        AddFilmNote::class,
        DeleteFilmNote::class,

        // Подборки
        ListCollections::class,
        GetCollection::class,
        SaveCollection::class,
        ManageCollectionFilms::class,
        DeleteCollection::class,
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        WhatToWatch::class,
        WatchlistDigest::class,
    ];
}
