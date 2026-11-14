<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Resources\Management\FranchiseResource;
use App\Models\Franchise;
use App\Services\ComposableTable\Paginable;
use App\Services\ComposableTable\Searchable;
use App\Services\ComposableTable\Sortable;
use Illuminate\Http\Request;

class FranchiseController extends Controller
{
    use Searchable, Paginable, Sortable;

    public function index(Request $request)
    {
        $data = $request->validate([
            ...$this->checkSearch(),
            ...$this->checkPage(),
            ...$this->checkSort([
                'id',
                'name',
                'films_count'
            ])
        ]);

        $query = Franchise::query()
                          ->withCount('films');

        $this->applySearch($data, $query);
        $this->applySort($data, $query);

        $query->orderBy('id');

        return FranchiseResource::collection($this->applyPagination($data, $query));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        Franchise::create([
            ...$data,
            'author_id' => auth()->id()
        ]);
    }

    public function show(Franchise $franchise)
    {
        // Студии тайтлов — для блока «кто делал» на странице франшизы.
        $franchise->load('films.companies');

        return new FranchiseResource($franchise);
    }

    /**
     * Статусы просмотра текущего пользователя по тайтлам франшизы — для отметок
     * на таймлайне. Отдельно от show по той же причине, что и у студий: show
     * публичный и одинаковый для всех, а статусы у каждого свои.
     *
     * @return array{data: array<int, string>} film_id => статус
     */
    public function watchStatuses(Request $request, Franchise $franchise)
    {
        $statuses = $request->user()
                            ->films()
                            ->whereIn('film_id', $franchise->films()->select('films.id'))
                            ->pluck('status', 'film_id')
                            ->map(fn($status) => $status->value ?? $status);

        return response()->json(['data' => (object)$statuses->all()]);
    }

    public function update(Request $request, Franchise $franchise)
    {
        if ($request->user()->cannot('update', $franchise))
            abort(403);

        $franchise->update($request->validate($this->rules()));
    }

    public function destroy(Request $request, Franchise $franchise)
    {
        if ($request->user()->cannot('delete', $franchise))
            abort(403);

        $franchise->delete();
    }

    private function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:65536',
            'link'        => 'nullable|string|url|max:255'
        ];
    }
}
