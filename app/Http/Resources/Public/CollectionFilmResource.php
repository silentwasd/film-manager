<?php

namespace App\Http\Resources\Public;

use App\Models\CollectionFilm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CollectionFilm */
class CollectionFilmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'note' => $this->note,
            'film' => new FilmResource($this->whenLoaded('film')),
        ];
    }
}
