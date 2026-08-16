<?php

namespace App\Mcp\Support;

use App\Models\Collection;
use App\Models\CollectionFilm;
use App\Models\Film;
use App\Models\FilmWatcher;
use App\Models\Person;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Единое место, где модели превращаются в структуры MCP-ответов.
 *
 * От API-ресурсов отличается тем, что все ссылки абсолютные: клиент MCP —
 * чужая языковая модель, у неё нет ни базового адреса фронта, ни знания
 * о том, что `cover` — это путь внутри `/storage`.
 */
class Presenter
{
    /** Постеров и фотографий в выдаче поиска нет — только в карточке. */
    public static function image(?string $path): ?string
    {
        return $path === null || $path === ''
            ? null
            : rtrim((string) config('app.url'), '/').'/storage/'.ltrim($path, '/');
    }

    public static function filmUrl(int $id): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/catalog/films/{$id}";
    }

    public static function personUrl(int $id): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/catalog/people/{$id}";
    }

    public static function companyUrl(int $id): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/catalog/companies/{$id}";
    }

    /**
     * Строка выдачи поиска: минимум полей, чтобы в контекст модели влезала
     * сотня фильмов, а не десяток.
     *
     * @return array<string, mixed>
     */
    public static function filmCard(Film $film): array
    {
        return [
            'id' => $film->id,
            'name' => $film->name,
            'original_name' => $film->original_name,
            'format' => $film->format?->value,
            'year' => $film->produced_year,
            'url' => self::filmUrl($film->id),
        ];
    }

    /**
     * Полная карточка. Связи ожидаются загруженными — вызывающий код обязан
     * сделать `with()`, иначе на списке в 50 фильмов это N+1.
     *
     * @return array<string, mixed>
     */
    public static function filmDetail(Film $film): array
    {
        return [
            ...self::filmCard($film),
            'release_date' => $film->release_date?->format('Y-m-d'),
            'description' => $film->description,
            'cover' => self::image($film->cover),
            'background_cover' => self::image($film->background_cover),
            'genres' => $film->relationLoaded('genres') ? $film->genres->pluck('name')->all() : [],
            'countries' => $film->relationLoaded('countries') ? $film->countries->pluck('name')->all() : [],
            'tags' => $film->relationLoaded('tags') ? $film->tags->pluck('name')->all() : [],
            'companies' => $film->relationLoaded('companies') ? $film->companies->pluck('name')->all() : [],
            'people' => $film->relationLoaded('people') ? self::filmPeople($film) : [],
        ];
    }

    /**
     * Люди фильма, сгруппированные по роли. Актёров в аниме бывает под сотню,
     * поэтому каждая роль обрезается — полный список берётся через get_person.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function filmPeople(Film $film, int $perRole = 15): array
    {
        return $film->people
            ->groupBy(fn ($filmPerson) => $filmPerson->role?->value ?? 'unknown')
            ->map(fn ($group) => $group
                ->take($perRole)
                ->map(fn ($filmPerson) => array_filter([
                    'id' => $filmPerson->person?->id,
                    'name' => $filmPerson->person?->name,
                    'as' => $filmPerson->role_details,
                ], fn ($value) => $value !== null))
                ->values()
                ->all()
            )
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function personCard(Person $person): array
    {
        return [
            'id' => $person->id,
            'name' => $person->name,
            'original_name' => $person->original_name,
            'url' => self::personUrl($person->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function personDetail(Person $person): array
    {
        return [
            ...self::personCard($person),
            'sex' => $person->sex?->value,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'death_date' => $person->death_date?->format('Y-m-d'),
            'country' => $person->country?->name,
            'photo' => self::image($person->photo),
        ];
    }

    /**
     * Реакция и отзыв лежат не в `film_watchers`, а в `feedback`, и приезжают
     * только если вызывающий код загрузил связь, ограничив её текущим
     * пользователем. Без этого ключей в ответе не будет вовсе — это честнее,
     * чем показать «нет реакции» там, где её просто не спрашивали.
     *
     * @return array<string, mixed>
     */
    public static function watcher(FilmWatcher $watcher): array
    {
        $result = [
            'film' => $watcher->relationLoaded('film') && $watcher->film
                ? self::filmCard($watcher->film)
                : ['id' => $watcher->film_id],
            'status' => $watcher->status,
            'updated_at' => $watcher->updated_at?->format('Y-m-d'),
        ];

        if (! $watcher->relationLoaded('film') || ! $watcher->film?->relationLoaded('feedbacks')) {
            return $result;
        }

        $feedback = $watcher->film->feedbacks->first();

        // null и 0 — разные вещи: null это «не оценивал», а 0 — поставленная
        // нейтральная оценка, ни за, ни против. Ключ есть всегда, когда связь
        // загружена, иначе отсутствие оценки не отличить от незагруженных данных.
        $result['reaction'] = $feedback === null ? null : (int) $feedback->reaction;

        if ($feedback?->text !== null && $feedback->text !== '') {
            $result['review'] = $feedback->text;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectionCard(Collection $collection): array
    {
        return array_filter([
            'id' => $collection->id,
            'key' => $collection->public_key,
            'name' => $collection->name,
            'description' => $collection->description,
            'visibility' => $collection->visibility?->value,
            'owner' => $collection->relationLoaded('user') ? $collection->user?->name : null,
            'films_count' => $collection->films_count ?? ($collection->relationLoaded('films') ? $collection->films->count() : null),
            'url' => $collection->relationLoaded('user') || $collection->visibility?->value === 'public'
                ? $collection->publicUrl()
                : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectionDetail(Collection $collection): array
    {
        return [
            ...self::collectionCard($collection),
            'films' => $collection->films
                ->map(fn (CollectionFilm $item) => array_filter([
                    'position' => $item->position,
                    'note' => $item->note,
                    ...($item->film ? self::filmCard($item->film) : ['id' => $item->film_id]),
                ], fn ($value) => $value !== null))
                ->values()
                ->all(),
        ];
    }

    /**
     * Обёртка постраничной выдачи. `total` и `pages` нужны модели, чтобы она
     * понимала, стоит ли листать дальше, и не выдумывала недостающее.
     *
     * @param  LengthAwarePaginator<int, covariant \Illuminate\Database\Eloquent\Model>  $paginator
     * @param  callable(mixed): array<string, mixed>  $mapper
     * @return array<string, mixed>
     */
    public static function page(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return [
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pages' => $paginator->lastPage(),
            'items' => collect($paginator->items())->map($mapper)->values()->all(),
        ];
    }
}
