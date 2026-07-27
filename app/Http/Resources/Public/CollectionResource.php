<?php

namespace App\Http\Resources\Public;

use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Collection */
class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'public_key' => $this->public_key,
            'author' => $this->whenLoaded('user', fn () => $this->user->name),
            'films_count' => $this->whenCounted('films'),
            'films' => CollectionFilmResource::collection($this->whenLoaded('films')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
