<?php

namespace App\Http\Resources\Public;

use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Коллекция в списке профиля: без фильмов, только карточка со ссылкой.
 *
 * @mixin Collection
 */
class CollectionCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'public_key' => $this->public_key,
            'visibility' => $this->visibility,
            'path' => $this->publicPath(),
            'films_count' => $this->whenCounted('films'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
