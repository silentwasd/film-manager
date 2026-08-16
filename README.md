# Film Manager

Headless REST API на Laravel 13 для управления каталогом фильмов. Предназначен для потребления отдельным SPA-фронтендом. Приложение локализовано на русский язык.

## Возможности

### Каталог фильмов
- Добавление, редактирование и удаление фильмов
- Привязка жанров, стран, компаний, тегов
- Поиск, сортировка, пагинация списков
- Модерация: статусы публикации (`FilmModerationStatus`)
- Форматы (`FilmCinemaStatus`, `FilmFormat`)

### Персоны
- Отдельный справочник людей (актёры, режиссёры и др.)
- Привязка к фильму через пивот с указанием роли, деталей и порядка

### Пользовательские списки и отзывы
- Список «Мои фильмы» со статусами просмотра: `to-watch`, `must-finish`, `watched`, `dropped`
- Реакция на фильм: `-1` (дизлайк), `0` (нейтрально), `1` (лайк)

### Права доступа
- **Публичные** эндпоинты — без авторизации (каталог, жанры, sitemap)
- **Пользователи** — редактируют только свои фильмы, управляют своим списком и отзывами (Laravel Sanctum)
- **Администраторы** — полный доступ к любым записям

## Стек

- PHP 8.3, Laravel 13
- MySQL 8.0
- Redis 7 (очереди)
- Laravel Sanctum (аутентификация API)
- Laravel MCP + Passport (MCP-сервер и OAuth для коннекторов)

## Установка

**Требования:** PHP 8.3, MySQL 8.0, Redis 7, Composer, Node.js

Целевая версия PHP зафиксирована в `composer.json` через `config.platform.php`.
Composer подбирает зависимости под неё, а не под ту, что стоит у разработчика, —
иначе на машине с PHP 8.5 в lock приезжает Symfony 8.1 (требует 8.4.1+),
и на боевом сервере `composer install` падает. Меняется версия на сервере —
меняется и это значение, следом `composer update`.

```bash
git clone https://github.com/silentwasd/film-manager.git
cd film-manager

composer install
npm install

cp .env.example .env
php artisan key:generate

# Настройте .env: DB_*, REDIS_*, APP_FRONTEND_URL
php artisan migrate
```

## Переменные окружения

| Переменная | Описание |
|---|---|
| `APP_FRONTEND_URL` | CORS origin для SPA-фронтенда |

## API

Все маршруты расположены в `routes/api.php` под префиксом `/api`.

| Группа | Префикс | Доступ |
|---|---|---|
| Public | `/api/films`, `/api/genre/{slug}`, `/api/sitemap` | Без авторизации |
| Management | `/api/management/...` | Sanctum-токен |
| Admin | `/api/management/...` (часть маршрутов) | Sanctum + роль admin |

## MCP-сервер

Каталог подключается к Claude, ChatGPT и Grok как коннектор: `POST /mcp`,
транспорт Streamable HTTP, протокол `2025-11-25`. Сервер описан в
`app/Mcp/Servers/FilmManagerServer.php`, инструменты — в `app/Mcp/Tools/`,
маршруты — в `routes/ai.php`.

### Инструменты

| Группа | Инструменты |
|---|---|
| Аккаунт | `whoami` |
| Каталог | `search_films`, `get_film`, `search_people`, `get_person`, `list_facets` |
| Общий поиск | `search`, `fetch` — обязательная пара для коннекторов ChatGPT |
| Список просмотра | `get_watchlist`, `set_watch_status`, `remove_from_watchlist` |
| Оценки и заметки | `set_film_reaction`, `add_film_note`, `delete_film_note` |
| Подборки | `list_collections`, `get_collection`, `save_collection`, `manage_collection_films`, `delete_collection` |

Плюс два prompt'а: `what_to_watch` и `watchlist_digest`.

Жанры, страны и теги инструменты принимают **названиями**, а не id: модель
оперирует словами, а не первичными ключами. Неизвестное название возвращается
в поле `warnings`, чтобы пустая выдача не выглядела как пустой каталог.

`get_watchlist` отдаёт оценку и отзыв прямо в строке списка. У оценки четыре
состояния: `1` понравилось, `-1` не понравилось, `0` нейтрально и `null` —
не оценивал. Ноль это поставленная оценка, а не её отсутствие, поэтому
фильтры разведены: `reaction` ищет сохранённое значение, `rated: false` —
тех, у кого оценки нет вовсе.

### Авторизация

OAuth 2.1 с PKCE и динамической регистрацией клиента — коннектор настраивается
одним URL, ничего заводить руками не нужно. Authorization server — Laravel
Passport на том же домене:

| Точка | Что отдаёт |
|---|---|
| `/.well-known/oauth-protected-resource/mcp` | метаданные ресурса, RFC 9728 |
| `/.well-known/oauth-authorization-server` | метаданные AS, RFC 8414 |
| `POST /oauth/register` | динамическая регистрация клиента, RFC 7591 |
| `GET /oauth/authorize` | вход и экран согласия |
| `POST /oauth/token` | обмен кода на токен |

Скоуп один — `mcp:use`. Токен живёт 30 дней, refresh — 180.

`config/mcp.php` перечисляет домены, куда разрешено возвращать пользователя
после согласия. Это единственная преграда между открытым `/oauth/register`
и посторонним клиентом с чужим `redirect_uri`, поэтому `'*'` там не стоит —
новый коннектор добавляется строкой.

Passport здесь **не заменяет Sanctum**: обычное API по-прежнему на нём,
у MCP отдельный гвард `mcp` (`config/auth.php`).

### Подключение

Адрес сервера — `https://<домен API>/mcp`.

- **Claude** — Settings → Connectors → Add custom connector, вставить URL.
- **ChatGPT** — Settings → Connectors (нужен developer mode), тот же URL.
- **Grok** — Settings → Connectors → Add MCP server.
- **Claude Code / Desktop** — `claude mcp add --transport http кинокот https://<домен>/mcp`.

Дальше клиент сам сходит за метаданными, зарегистрирует себя и покажет
кнопку «Connect»: она ведёт на страницу входа каталога, а после неё —
на экран согласия.

### Установка

```bash
composer install
php artisan migrate          # пять таблиц oauth_*
php artisan passport:keys    # ключи подписи токенов
```

Ключи ложатся в `storage/oauth-{private,public}.key` и в git не попадают.
Их должен читать веб-сервер:

```bash
sudo chgrp www-data storage/oauth-*.key && sudo chmod 640 storage/oauth-*.key
```

Nginx: `/.well-known/` должен доходить до `index.php`, а типовое правило
`location ~ /\. { deny all; }` его съедает. Достаточно вырезать исключение
прямо в нём:

```nginx
location ~ /\.(?!well-known/) {
    deny all;
}
```

Слэш в lookahead обязателен: без него правило пропустит и `/.well-known-что-угодно`.

Отозванные токены и мусорные клиенты чистит `php artisan passport:purge`.

`php artisan route:cache` использовать нельзя: маршруты MCP объявлены замыканиями
и не сериализуются. В этом проекте кэш маршрутов и так не собирался — имя
`films.index` занято дважды в `routes/api.php`.

## Тесты

```bash
php artisan test --env=testing
```
