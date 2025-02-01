<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Resources\Management\CompanyResource;
use App\Models\Company;
use App\Services\ComposableTable\Paginable;
use App\Services\ComposableTable\Searchable;
use App\Services\ComposableTable\Sortable;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use Searchable, Paginable, Sortable;

    public function index(Request $request)
    {
        $data = $request->validate([
            ...$this->checkSearch(),
            ...$this->checkPage(),
            ...$this->checkSort([
                'id',
                'name'
            ])
        ]);

        $query = Company::query();

        $this->applySearch($data, $query);
        $this->applySort($data, $query);

        $query->orderBy('id');

        return CompanyResource::collection($this->applyPagination($data, $query));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:65536',
            'link'        => 'nullable|string|url|max:255'
        ]);

        Company::create([
            ...$data,
            'author_id' => auth()->id()
        ]);
    }

    public function show(Company $company)
    {
        $company->load('films');

        return new CompanyResource($company);
    }

    public function update(Request $request, Company $company)
    {
        if ($request->user()->cannot('update', $company))
            abort(403);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:65536',
            'link'        => 'nullable|string|url|max:255'
        ]);

        $company->update($data);
    }

    public function destroy(Request $request, Company $company)
    {
        if ($request->user()->cannot('delete', $company))
            abort(403);

        $company->delete();
    }
}
