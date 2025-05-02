<?php

namespace App\Http\Resources\Public;

use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Genre */
class GenreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'        => $this->name,
            'icon'        => $this->icon,
            'description' => $this->description,
            'films'       => FilmResource::collection(
                $this->films()
                     ->whereNotNull('cover')
                     ->whereNotNull('produced_year')
                     ->orderByDesc('produced_year')
                     ->get()
            )
        ];
    }
}
