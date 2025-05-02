<?php

namespace App\Http\Controllers\Management;

use App\Enums\FilmFormat;
use App\Enums\FilmWatchStatus;
use App\Enums\PersonRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Management\FilmWatcherResource;
use App\Models\Film;
use App\Models\FilmWatcher;
use App\Services\ComposableTable\Paginable;
use App\Services\ComposableTable\Searchable;
use App\Services\ComposableTable\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FilmWatcherController extends Controller
{
    use Searchable, Paginable, Sortable;

    public function index(Request $request)
    {
        $data = $request->validate([
            ...$this->checkSearch(),
            ...$this->checkPage(),
            ...$this->checkSort([
                'id',
                'film.name',
                'film.format',
                'film.produced_year'
            ]),
            'watch_status' => ['nullable', Rule::enum(FilmWatchStatus::class)],
            'reaction'     => ['nullable', 'integer', 'min:-1', 'max:1'],
            'format'       => ['nullable', Rule::enum(FilmFormat::class)],
            'people'       => ['nullable', 'array', 'exists:people,id'],
            'genres'       => ['nullable', 'array', 'exists:genres,id'],
            'countries'    => ['nullable', 'array', 'exists:countries,id'],
            'tags'         => ['nullable', 'array', 'exists:tags,id']
        ]);

        $query = $request->user()
                         ->films()
                         ->with(['film', 'film.people', 'film.people.person'])
                         ->getQuery();

        $query
            ->when($data['name'] ?? false, fn(Builder $when) => $when
                ->whereHas('film', fn(Builder $has) => $has
                    ->where('name', 'LIKE', '%' . $data['name'] . '%')
                    ->orWhere('original_name', 'LIKE', '%' . $data['name'] . '%')
                )
            )->when($data['watch_status'] ?? false, fn(Builder $when) => $when
                ->where('status', $data['watch_status'])
            )->when(isset($data['reaction']) && $data['reaction'] != 0, fn(Builder $when) => $when
                ->whereHas('film.feedbacks', fn(Builder $has) => $has
                    ->where('feedback.user_id', request()->user()->id)
                    ->where('feedback.reaction', $data['reaction'])
                )
            )->when(isset($data['reaction']) && $data['reaction'] == 0, fn(Builder $when) => $when
                ->where(fn(Builder $where) => $where
                    ->whereDoesntHave('film.feedbacks', fn(Builder $has) => $has
                        ->where('feedback.user_id', request()->user()->id)
                    )->orWhereHas('film.feedbacks', fn(Builder $has) => $has
                        ->where('feedback.user_id', request()->user()->id)
                        ->where('feedback.reaction', 0)
                    )
                )
            )->when($data['format'] ?? false, fn(Builder $when) => $when
                ->whereHas('film', fn(Builder $has) => $has->where('format', $data['format']))
            )->when($data['people'] ?? false, fn(Builder $when) => $when
                ->whereHas('film.people', fn(Builder $has) => $has
                    ->whereIn('film_people.person_id', $data['people'])
                )
            )->when($data['genres'] ?? false, fn(Builder $when) => $when
                ->whereHas('film.genres', fn(Builder $has) => $has
                    ->whereIn('film_genre.genre_id', $data['genres'])
                )
            )->when($data['countries'] ?? false, fn(Builder $when) => $when
                ->whereHas('film.countries', fn(Builder $has) => $has
                    ->whereIn('country_film.country_id', $data['countries'])
                )
            )->when($data['tags'] ?? false, fn(Builder $when) => $when
                ->whereHas('film.tags', fn(Builder $has) => $has
                    ->whereIn('film_tag.tag_id', $data['tags'])
                )
            );

        $this->applySort($data, $query);

        $query->orderBy('id');

        return FilmWatcherResource::collection($this->applyPagination($data, $query));
    }

    protected function sort(Builder $query, string $column, string $direction): Builder
    {
        return match ($column) {
            'film.name'          => $query->with('film')
                                          ->orderBy(
                                              Film::select('name')
                                                  ->whereColumn('films.id', 'film_watchers.film_id')
                                                  ->limit(1),
                                              $direction
                                          ),
            'film.format'        => $query->with('film')
                                          ->orderBy(
                                              Film::select('format')
                                                  ->whereColumn('films.id', 'film_watchers.film_id')
                                                  ->limit(1),
                                              $direction
                                          ),
            'film.produced_year' => $query->with('film')
                                          ->orderBy(
                                              Film::select('produced_year')
                                                  ->whereColumn('films.id', 'film_watchers.film_id')
                                                  ->limit(1),
                                              $direction
                                          ),
            default              => $query->orderBy($column, $direction),
        };
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'film_id' => 'required|integer|exists:films,id',
            'status'  => ['required', Rule::enum(FilmWatchStatus::class)]
        ]);

        $request->user()->films()->create($data);
    }

    public function byFilm(Request $request, Film $film)
    {
        $watcher = $request->user()->films()->where('film_id', $film->id)->first();

        if (!$watcher)
            abort(404);

        return new FilmWatcherResource($watcher);
    }

    public function update(Request $request, FilmWatcher $filmWatcher)
    {
        if ($request->user()->cannot('update', $filmWatcher))
            abort(403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(FilmWatchStatus::class)]
        ]);

        $filmWatcher->update($data);
    }

    public function destroy(Request $request, FilmWatcher $filmWatcher)
    {
        if ($request->user()->cannot('delete', $filmWatcher))
            abort(403);

        $filmWatcher->delete();
    }
}
