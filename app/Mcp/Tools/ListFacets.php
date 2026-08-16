<?php

namespace App\Mcp\Tools;

use App\Enums\FilmFormat;
use App\Enums\FilmWatchStatus;
use App\Enums\PersonRole;
use App\Models\Company;
use App\Models\Country;
use App\Models\Genre;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_facets')]
#[Title('Справочники каталога')]
#[Description(
    'Отдаёт допустимые значения фильтров: жанры, страны, теги, студии, форматы, '.
    'роли участников, статусы просмотра. Вызывать до search_films, если непонятно, '.
    'как в этом каталоге называется жанр или страна.'
)]
#[IsReadOnly]
#[IsIdempotent]
class ListFacets extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'kind' => 'required|string|in:genres,countries,tags,companies,formats,person_roles,watch_statuses',
            'query' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $limit = $data['limit'] ?? 100;
        $search = $data['query'] ?? null;

        $values = match ($data['kind']) {
            'genres' => $this->names(Genre::class, $search, $limit),
            'countries' => $this->names(Country::class, $search, $limit),
            'tags' => $this->names(Tag::class, $search, $limit),
            // Компаний больше полутысячи, поэтому без запроса отдаём самые
            // заметные — по числу фильмов в каталоге.
            'companies' => Company::query()
                ->when($search, fn ($query) => $query->where('name', 'LIKE', '%'.$search.'%'))
                ->withCount('films')
                ->orderByDesc('films_count')
                ->orderBy('name')
                ->limit($limit)
                ->pluck('name')
                ->all(),
            'formats' => array_column(FilmFormat::cases(), 'value'),
            'person_roles' => array_column(PersonRole::cases(), 'value'),
            'watch_statuses' => array_column(FilmWatchStatus::cases(), 'value'),
        };

        return Response::json([
            'kind' => $data['kind'],
            'count' => count($values),
            'values' => $values,
        ]);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return array<int, string>
     */
    private function names(string $model, ?string $search, int $limit): array
    {
        return $model::query()
            ->when($search, fn ($query) => $query->where('name', 'LIKE', '%'.$search.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->enum(['genres', 'countries', 'tags', 'companies', 'formats', 'person_roles', 'watch_statuses'])
                ->description('Какой справочник вернуть.')
                ->required(),
            'query' => $schema->string()
                ->description('Отфильтровать по подстроке названия. Работает для genres, countries, tags, companies.'),
            'limit' => $schema->integer()->description('Сколько значений вернуть, до 200. По умолчанию 100.'),
        ];
    }
}
