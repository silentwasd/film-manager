<?php

namespace App\Http\Controllers\Management;

use App\Enums\PersonRole;
use App\Enums\PersonSex;
use App\Http\Controllers\Controller;
use App\Http\Resources\Management\PersonResource;
use App\Models\Genre;
use App\Models\Person;
use App\Services\ComposableTable\Paginable;
use App\Services\ComposableTable\Searchable;
use App\Services\ComposableTable\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PersonController extends Controller
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
                'birth_date',
                'death_date'
            ]),
            'role'      => ['nullable', Rule::enum(PersonRole::class)],
            'countries' => ['nullable', 'array', 'exists:countries,id']
        ]);

        $query = Person::query()
                       ->when($data['role'] ?? null, fn(Builder $when) => $when
                           ->whereHas('films', fn(Builder $has) => $has
                               ->where('film_people.role', $data['role'])
                           )
                       )
                       ->when($data['countries'] ?? false, fn(Builder $when) => $when
                           ->whereIn('country_id', $data['countries'])
                       )
                       ->when($data['name'] ?? false, fn(Builder $when) => $when
                           ->where('name', 'LIKE', '%' . $data['name'] . '%')
                           ->orWhere('original_name', 'LIKE', '%' . $data['name'] . '%')
                       )
                       ->when($data['model_id'] ?? [], fn(Builder $when) => $when
                           ->whereIn('id', is_array($data['model_id']) ? $data['model_id'] : [$data['model_id']], ($data['name'] ?? false) ? 'OR' : 'AND')
                       )
                       ->with(['country', 'films'])
                       ->withCount(['films as films_count' => function ($query) {
                           $query->select(DB::raw('count(distinct film_id)'));
                       }]);

        $this->applySort($data, $query);

        $query->orderBy('id');

        return PersonResource::collection($this->applyPagination($data, $query));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'original_name' => 'nullable|string|max:255',
            'birth_date'    => 'nullable|date',
            'death_date'    => 'nullable|date',
            'sex'           => ['nullable', Rule::enum(PersonSex::class)],
            'photo'         => 'nullable|image|max:10240',
            'country_id'    => 'nullable|exists:countries,id'
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('people', 'public');
        }

        Person::create([
            ...$data,
            'author_id' => $request->user()->id
        ]);
    }

    public function show(Person $person)
    {
        $person->load(['films', 'films.film', 'country'])
               ->loadCount('films');

        $genres = Genre::whereHas('films.people', fn($q) => $q->where('person_id', $person->id))
                       ->withCount([
                           'films as film_count' => fn($q) => $q->whereHas('people', fn($q2) => $q2
                               ->where('person_id', $person->id)
                           )
                       ])
                       ->orderByDesc('film_count')
                       ->get();

        return (new PersonResource($person))->additional(['genres' => $genres]);
    }

    public function update(Request $request, Person $person)
    {
        if ($request->user()->cannot('update', $person))
            abort(403);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'original_name' => 'nullable|string|max:255',
            'birth_date'    => 'nullable|date',
            'death_date'    => 'nullable|date',
            'sex'           => ['nullable', Rule::enum(PersonSex::class)],
            'photo'         => 'nullable|image|max:10240',
            'country_id'    => 'nullable|exists:countries,id'
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('people', 'public');
        } else {
            $data['photo'] = $person->photo;
        }

        $person->update($data);
    }

    public function destroy(Request $request, Person $person)
    {
        if ($request->user()->cannot('delete', $person))
            abort(403);

        $person->delete();
    }
}
