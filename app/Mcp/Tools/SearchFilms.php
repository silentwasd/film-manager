<?php

namespace App\Mcp\Tools;

use App\Enums\FilmFormat;
use App\Mcp\Support\Presenter;
use App\Mcp\Support\ResolvesTaxonomies;
use App\Models\Country;
use App\Models\Film;
use App\Models\Genre;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_films')]
#[Title('Поиск фильмов')]
#[Description(
    'Ищет фильмы, сериалы и мультфильмы в каталоге. Фильтры комбинируются по И: '.
    'жанры, страны, теги, участник, студия, диапазон годов, формат. '.
    'Жанры, страны и теги задаются названиями, а не id; список доступных значений '.
    'даёт list_facets. Возвращает короткие карточки — за подробностями идти в get_film.'
)]
#[IsReadOnly]
#[IsIdempotent]
class SearchFilms extends Tool
{
    use ResolvesTaxonomies;

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'query' => 'nullable|string|max:255',
            'format' => ['nullable', 'string', 'in:'.implode(',', array_column(FilmFormat::cases(), 'value'))],
            'genres' => 'nullable|array|max:10',
            'genres.*' => 'string|max:255',
            'countries' => 'nullable|array|max:10',
            'countries.*' => 'string|max:255',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:255',
            'person' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'year_from' => 'nullable|integer|min:1888|max:2100',
            'year_to' => 'nullable|integer|min:1888|max:2100',
            'sort' => 'nullable|string|in:year_desc,year_asc,name,newest',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $warnings = [];
        $query = Film::query();

        if ($name = $data['query'] ?? null) {
            $query->where(fn (Builder $where) => $where
                ->where('name', 'LIKE', '%'.$name.'%')
                ->orWhere('original_name', 'LIKE', '%'.$name.'%')
            );
        }

        if ($format = $data['format'] ?? null) {
            $query->where('format', $format);
        }

        foreach ([
            ['genres', Genre::class, 'genres', 'film_genre.genre_id'],
            ['countries', Country::class, 'countries', 'country_film.country_id'],
            ['tags', Tag::class, 'tags', 'film_tag.tag_id'],
        ] as [$key, $model, $relation, $column]) {
            $names = $data[$key] ?? [];

            if ($names === []) {
                continue;
            }

            $ids = $this->idsByName($model, $names);

            if ($ids === []) {
                $warnings[] = "В справочнике нет таких значений для «{$key}»: ".implode(', ', $names).
                    '. Полный список — list_facets.';
            }

            $query->whereHas($relation, fn (Builder $has) => $has->whereIn($column, $ids));
        }

        if ($person = $data['person'] ?? null) {
            $query->whereHas('people.person', fn (Builder $has) => $has
                ->where('name', 'LIKE', '%'.$person.'%')
                ->orWhere('original_name', 'LIKE', '%'.$person.'%')
            );
        }

        if ($company = $data['company'] ?? null) {
            $query->whereHas('companies', fn (Builder $has) => $has->where('name', 'LIKE', '%'.$company.'%'));
        }

        if ($from = $data['year_from'] ?? null) {
            $query->where('produced_year', '>=', $from);
        }

        if ($to = $data['year_to'] ?? null) {
            $query->where('produced_year', '<=', $to);
        }

        match ($data['sort'] ?? 'year_desc') {
            'year_asc' => $query->orderByRaw('produced_year IS NULL, produced_year ASC'),
            'name' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('id'),
            default => $query->orderByRaw('produced_year IS NULL, produced_year DESC'),
        };

        $query->orderBy('id');

        $page = Presenter::page(
            $query->paginate(perPage: $data['per_page'] ?? 20, page: $data['page'] ?? 1),
            fn (Film $film) => Presenter::filmCard($film)
        );

        return Response::json($warnings === [] ? $page : [...$page, 'warnings' => $warnings]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Часть названия — русского или оригинального.'),
            'format' => $schema->string()
                ->enum(array_column(FilmFormat::cases(), 'value'))
                ->description('film — полнометражный фильм, series — сериал, mini-series — мини-сериал, cartoon — полнометражный мультфильм, cartoon-series — мультсериал (в том числе аниме).'),
            'genres' => $schema->array()->items($schema->string())
                ->description('Названия жанров, например ["драма", "боевик"]. Фильм должен подходить под каждый.'),
            'countries' => $schema->array()->items($schema->string())
                ->description('Названия стран производства.'),
            'tags' => $schema->array()->items($schema->string())
                ->description('Названия тегов каталога.'),
            'person' => $schema->string()
                ->description('Имя участника: режиссёра, актёра, композитора. Роль не различается.'),
            'company' => $schema->string()
                ->description('Название студии или компании.'),
            'year_from' => $schema->integer()->description('Год производства не раньше.'),
            'year_to' => $schema->integer()->description('Год производства не позже.'),
            'sort' => $schema->string()
                ->enum(['year_desc', 'year_asc', 'name', 'newest'])
                ->description('year_desc — сначала свежие (по умолчанию), newest — сначала недавно добавленные в каталог.'),
            'page' => $schema->integer()->description('Номер страницы, с единицы.'),
            'per_page' => $schema->integer()->description('Размер страницы, до 50. По умолчанию 20.'),
        ];
    }
}
