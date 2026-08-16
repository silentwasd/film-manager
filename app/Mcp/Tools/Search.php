<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Presenter;
use App\Models\Collection;
use App\Models\Film;
use App\Models\Person;
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

/**
 * Пара search/fetch — обязательный контракт коннекторов ChatGPT: режим
 * исследования вызывает именно эти два имени и ждёт именно такую форму ответа.
 * Для остальных клиентов это просто общий поиск по каталогу.
 *
 * @see https://platform.openai.com/docs/mcp
 */
#[Name('search')]
#[Title('Общий поиск по каталогу')]
#[Description(
    'Ищет по всему каталогу сразу — фильмы, людей и подборки — и отдаёт список '.
    'ссылок с идентификаторами для fetch. Для фильтров по жанру, году или стране '.
    'лучше подходит search_films.'
)]
#[IsReadOnly]
#[IsIdempotent]
class Search extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $query = $data['query'];

        $films = Film::query()
            ->where(fn (Builder $where) => $where
                ->where('name', 'LIKE', '%'.$query.'%')
                ->orWhere('original_name', 'LIKE', '%'.$query.'%')
            )
            ->orderByRaw('produced_year IS NULL, produced_year DESC')
            ->limit(20)
            ->get()
            ->map(fn (Film $film) => [
                'id' => 'film:'.$film->id,
                'title' => trim($film->name.' ('.($film->produced_year ?? '—').')'),
                'url' => Presenter::filmUrl($film->id),
            ]);

        $people = Person::query()
            ->where(fn (Builder $where) => $where
                ->where('name', 'LIKE', '%'.$query.'%')
                ->orWhere('original_name', 'LIKE', '%'.$query.'%')
            )
            ->withCount('films')
            ->orderByDesc('films_count')
            ->limit(10)
            ->get()
            ->map(fn (Person $person) => [
                'id' => 'person:'.$person->id,
                'title' => $person->name,
                'url' => Presenter::personUrl($person->id),
            ]);

        $collections = Collection::query()
            ->visible()
            ->with('user')
            ->where('name', 'LIKE', '%'.$query.'%')
            ->limit(5)
            ->get()
            ->map(fn (Collection $collection) => [
                'id' => 'collection:'.$collection->id,
                'title' => 'Подборка: '.$collection->name,
                'url' => $collection->publicUrl(),
            ]);

        return Response::json([
            'results' => $films->concat($people)->concat($collections)->values()->all(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Поисковая строка: название фильма, имя человека или название подборки.')
                ->required(),
        ];
    }
}
