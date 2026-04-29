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
- Laravel Sanctum (аутентификация)

## Установка

**Требования:** PHP 8.3, MySQL 8.0, Redis 7, Composer, Node.js

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

## Тесты

```bash
php artisan test --env=testing
```
