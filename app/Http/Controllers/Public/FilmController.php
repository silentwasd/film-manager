<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\FilmResource;
use App\Models\Film;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FilmController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1'
        ]);

        return FilmResource::collection(
            Film::whereNotNull('cover')
                ->whereNotNull('produced_year')
                ->where('produced_year', '<=', now()->year)
                ->when($data['name'] ?? false, fn(Builder $when) => $when
                    ->where('name', 'LIKE', '%' . $data['name'] . '%')
                    ->orWhere('original_name', 'LIKE', '%' . $data['name'] . '%')
                )
                ->orderByDesc('produced_year')
                ->orderBy('id')
                ->paginate(perPage: 50, page: $data['page'] ?? 1)
        );
    }
}
