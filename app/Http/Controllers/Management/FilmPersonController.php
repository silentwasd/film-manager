<?php

namespace App\Http\Controllers\Management;

use App\Enums\PersonRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Management\FilmPersonResource;
use App\Models\Film;
use App\Models\FilmPerson;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FilmPersonController extends Controller
{
    public function index(Film $film)
    {
        return FilmPersonResource::collection(
            $film->people()
                 ->orderByRaw("FIELD(role, 'director', 'actor', 'voice-actor', 'producer', 'screenwriter', 'operator', 'artist', 'editor', 'composer', 'sound-director', 'dubbing-director', 'dubbing-actor', 'translator')")
                 ->orderBy('order_id')
                 ->orderBy('id')
                 ->with('person')
                 ->get()
        );
    }

    public function store(Request $request, Film $film)
    {
        if ($request->user()->cannot('create', [FilmPerson::class, $film]))
            abort(403);

        $data = $request->validate([
            'person_id'    => 'required|exists:people,id',
            'role'         => ['required', Rule::enum(PersonRole::class)],
            'role_details' => 'nullable|string|max:255'
        ]);

        $film->people()->create([
            ...$data,
            'order_id' => (FilmPerson::latest('order_id')->first()?->order_id ?? 0) + 1
        ]);
    }

    public function update(Request $request, Film $film, FilmPerson $person)
    {
        if ($request->user()->cannot('update', $person))
            abort(403);

        $data = $request->validate([
            'person_id'    => 'required|exists:people,id',
            'role'         => ['required', Rule::enum(PersonRole::class)],
            'role_details' => 'nullable|string|max:255'
        ]);

        $person->update($data);
    }

    public function destroy(Request $request, Film $film, FilmPerson $person)
    {
        if ($request->user()->cannot('delete', $person))
            abort(403);

        $person->delete();
    }
}
