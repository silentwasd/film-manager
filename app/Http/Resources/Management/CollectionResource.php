<?php

namespace App\Http\Resources\Management;

use App\Enums\CollectionVisibility;
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
            'visibility' => $this->visibility,
            'public_key' => $this->public_key,
            'public_url' => $this->when(
                $this->visibility !== CollectionVisibility::Hidden,
                fn () => $this->publicUrl()
            ),
            'films_count' => $this->whenCounted('films'),
            'films' => CollectionFilmResource::collection($this->whenLoaded('films')),
            'can_edit' => auth()->user()->can('update', $this->resource),
        ];
    }
}
