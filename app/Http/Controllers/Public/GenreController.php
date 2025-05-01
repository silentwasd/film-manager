<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\GenreResource;
use App\Models\Genre;

class GenreController extends Controller
{
    public function show(Genre $genre)
    {
        return new GenreResource($genre);
    }
}
