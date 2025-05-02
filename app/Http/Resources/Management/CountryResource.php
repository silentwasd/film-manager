<?php

namespace App\Http\Resources\Management;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Country */
class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'films_count'  => $this->whenCounted('films'),
            'people_count' => $this->whenCounted('people')
        ];
    }
}
