<?php

namespace App\Http\Controllers\Management;

use App\Enums\CollectionVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\Management\CollectionResource;
use App\Models\Collection;
use App\Services\ComposableTable\Paginable;
use App\Services\ComposableTable\Searchable;
use App\Services\ComposableTable\Sortable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->with('user')
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

        $collection->load([
            'user',
            'films' => fn ($q) => $q->with('film', 'film.people', 'film.people.person')->orderBy('position'),
        ]);

        return new CollectionResource($collection);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $collection = $request->user()->collections()->create($data);

        // Связь нужна, чтобы собрать вложенный путь личной коллекции,
        // и она уже под рукой — лишний запрос ни к чему.
        $collection->setRelation('user', $request->user());

        return new CollectionResource($collection);
    }

    public function update(Request $request, Collection $collection)
    {
        if ($request->user()->cannot('update', $collection)) {
            abort(403);
        }

        $data = $request->validate($this->rules());

        $collection->update($data);

        $collection->setRelation('user', $request->user());

        return new CollectionResource($collection);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'visibility' => ['sometimes', Rule::enum(CollectionVisibility::class)],
        ];
    }

    public function destroy(Request $request, Collection $collection)
    {
        if ($request->user()->cannot('delete', $collection)) {
            abort(403);
        }

        $collection->delete();
    }
}
