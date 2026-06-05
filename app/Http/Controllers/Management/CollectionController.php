<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Resources\Management\CollectionResource;
use App\Models\Collection;
use App\Services\ComposableTable\Paginable;
use App\Services\ComposableTable\Searchable;
use App\Services\ComposableTable\Sortable;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    use Paginable, Searchable, Sortable;

    public function index(Request $request)
    {
        $data = $request->validate([
            ...$this->checkSearch(),
            ...$this->checkPage(),
            ...$this->checkSort(['id', 'name']),
        ]);

        $query = $request->user()
            ->collections()
            ->withCount('films')
            ->getQuery();

        $this->applySearch($data, $query);
        $this->applySort($data, $query);
        $query->orderBy('id');

        return CollectionResource::collection($this->applyPagination($data, $query));
    }

    public function show(Request $request, Collection $collection)
    {
        if ($request->user()->cannot('update', $collection)) {
            abort(403);
        }

        $collection->load(['films' => fn ($q) => $q->with('film', 'film.people', 'film.people.person')->orderBy('position')]);

        return new CollectionResource($collection);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $collection = $request->user()->collections()->create($data);

        return new CollectionResource($collection);
    }

    public function update(Request $request, Collection $collection)
    {
        if ($request->user()->cannot('update', $collection)) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $collection->update($data);

        return new CollectionResource($collection);
    }

    public function destroy(Request $request, Collection $collection)
    {
        if ($request->user()->cannot('delete', $collection)) {
            abort(403);
        }

        $collection->delete();
    }
}
