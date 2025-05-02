<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Resources\Management\CountryResource;
use App\Models\Country;
use App\Services\ComposableTable\Paginable;
use App\Services\ComposableTable\Searchable;
use App\Services\ComposableTable\Sortable;
use Illuminate\Http\Request;

class CountryController extends Controller
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
                'films_count',
                'people_count'
            ])
        ]);

        $query = Country::query()
                        ->withCount('films', 'people');

        $this->applySearch($data, $query);
        $this->applySort($data, $query);

        return CountryResource::collection($this->applyPagination($data, $query));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        Country::create($data);
    }

    public function update(Request $request, Country $Country)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $Country->update($data);
    }

    public function destroy(Country $Country)
    {
        $Country->delete();
    }
}
