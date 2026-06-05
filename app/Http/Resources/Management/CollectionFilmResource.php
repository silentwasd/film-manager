<?php

namespace App\Http\Resources\Management;

use App\Models\CollectionFilm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CollectionFilm */
class CollectionFilmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'film_id' => $this->film_id,
            'position' => $this->position,
            'note' => $this->note,
            'film' => new FilmResource($this->whenLoaded('film')),
        ];
    }
}
