<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Resources\Management\CollectionFilmResource;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectionFilmController extends Controller
{
    public function store(Request $request, Collection $collection)
    {
        if ($request->user()->cannot('manageFilms', $collection)) {
            abort(403);
        }

        $maxPosition = $collection->films()->max('position') ?? 0;

        $data = $request->validate([
            'film_id' => [
                'required',
                'integer',
                'exists:films,id',
                Rule::unique('collection_films')->where('collection_id', $collection->id),
            ],
            'note' => 'nullable|string|max:512',
            'position' => ['nullable', 'integer', 'min:1', 'max:'.($maxPosition + 1)],
        ]);

        $position = $data['position'] ?? ($maxPosition + 1);

        if ($position <= $maxPosition) {
            $collection->films()
                ->where('position', '>=', $position)
                ->increment('position');
        }

        $collectionFilm = $collection->films()->create([
            'film_id' => $data['film_id'],
            'position' => $position,
            'note' => $data['note'] ?? null,
        ]);

        $collectionFilm->load('film', 'film.people', 'film.people.person');

        return new CollectionFilmResource($collectionFilm);
    }

    public function update(Request $request, Collection $collection, int $film)
    {
        if ($request->user()->cannot('manageFilms', $collection)) {
            abort(403);
        }

        $data = $request->validate([
            'note' => 'nullable|string|max:512',
        ]);

        $collectionFilm = $collection->films()->where('film_id', $film)->firstOrFail();
        $collectionFilm->update(['note' => $data['note']]);

        return new CollectionFilmResource($collectionFilm);
    }

    public function destroy(Request $request, Collection $collection, int $film)
    {
        if ($request->user()->cannot('manageFilms', $collection)) {
            abort(403);
        }

        $collectionFilm = $collection->films()->where('film_id', $film)->firstOrFail();
        $collectionFilm->delete();

        $collection->films()
            ->orderBy('position')
            ->get()
            ->each(fn ($cf, $idx) => $cf->update(['position' => $idx + 1]));
    }

    public function reorder(Request $request, Collection $collection)
    {
        if ($request->user()->cannot('manageFilms', $collection)) {
            abort(403);
        }

        $collectionFilmIds = $collection->films()->pluck('film_id')->toArray();

        $data = $request->validate([
            'films' => ['required', 'array', 'size:'.count($collectionFilmIds)],
            'films.*' => ['integer', Rule::in($collectionFilmIds)],
        ]);

        foreach ($data['films'] as $index => $filmId) {
            $collection->films()
                ->where('film_id', $filmId)
                ->update(['position' => $index + 1]);
        }
    }
}
