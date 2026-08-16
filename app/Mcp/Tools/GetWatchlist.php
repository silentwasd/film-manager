<?php

namespace App\Mcp\Tools;

use App\Enums\FilmWatchStatus;
use App\Mcp\Support\Presenter;
use App\Models\FilmWatcher;
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

#[Name('get_watchlist')]
#[Title('Мой список просмотра')]
#[Description(
    'Список фильмов текущего пользователя со статусами: watched — просмотрено, '.
    'to-watch — хочу посмотреть, must-finish — нужно досмотреть, dropped — брошено. '.
    'Фильтруется по статусу, жанру и году; сюда же попадают своя реакция и заметки.'
)]
#[IsReadOnly]
#[IsIdempotent]
class GetWatchlist extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(FilmWatchStatus::cases(), 'value'))],
            'query' => 'nullable|string|max:255',
            'genre' => 'nullable|string|max:255',
            'year_from' => 'nullable|integer|min:1888|max:2100',
            'year_to' => 'nullable|integer|min:1888|max:2100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $user = $request->user();

        $paginator = FilmWatcher::query()
            ->where('watcher_id', $user->getAuthIdentifier())
            ->with('film')
            ->when($data['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($data['query'] ?? null, fn (Builder $query, string $name) => $query
                ->whereHas('film', fn (Builder $has) => $has
                    ->where(fn (Builder $where) => $where
                        ->where('name', 'LIKE', '%'.$name.'%')
                        ->orWhere('original_name', 'LIKE', '%'.$name.'%')
                    )
                )
            )
            ->when($data['genre'] ?? null, fn (Builder $query, string $genre) => $query
                ->whereHas('film.genres', fn (Builder $has) => $has->where('name', 'LIKE', '%'.$genre.'%'))
            )
            ->when($data['year_from'] ?? null, fn (Builder $query, int $year) => $query
                ->whereHas('film', fn (Builder $has) => $has->where('produced_year', '>=', $year))
            )
            ->when($data['year_to'] ?? null, fn (Builder $query, int $year) => $query
                ->whereHas('film', fn (Builder $has) => $has->where('produced_year', '<=', $year))
            )
            ->latest('id')
            ->paginate(perPage: $data['per_page'] ?? 25, page: $data['page'] ?? 1);

        return Response::json(Presenter::page(
            $paginator,
            fn (FilmWatcher $watcher) => Presenter::watcher($watcher)
        ));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_column(FilmWatchStatus::cases(), 'value'))
                ->description('Оставить только записи с этим статусом.'),
            'query' => $schema->string()->description('Часть названия фильма.'),
            'genre' => $schema->string()->description('Название жанра.'),
            'year_from' => $schema->integer()->description('Год производства не раньше.'),
            'year_to' => $schema->integer()->description('Год производства не позже.'),
            'page' => $schema->integer()->description('Номер страницы, с единицы.'),
            'per_page' => $schema->integer()->description('Размер страницы, до 50. По умолчанию 25.'),
        ];
    }
}
