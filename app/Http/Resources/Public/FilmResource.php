<?php

namespace App\Http\Resources\Public;

use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Film */
class FilmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'format'        => $this->format,
            'cover'         => $this->cover,
            'produced_year' => $this->produced_year,
            'release_date'  => $this->release_date?->format('Y'),
            'description'   => $this->description,
            'cinema_status' => $this->cinema_status
        ];
    }
}
