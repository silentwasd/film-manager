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
        // Адрес публичный, токен необязателен: вошедшему отдаём, его ли это
        // коллекция и добавлена ли она к нему — от этого зависит кнопка.
        $viewer = $request->user('sanctum');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'public_key' => $this->public_key,
            'visibility' => $this->visibility,
            'path' => $this->whenLoaded('user', fn () => $this->publicPath()),
            'author' => $this->whenLoaded('user', fn () => [
                'name' => $this->user->name,
                'public_key' => $this->user->public_key,
            ]),
            'films_count' => $this->whenCounted('films'),
            'films' => CollectionFilmResource::collection($this->whenLoaded('films')),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'viewer' => $this->when($viewer !== null, fn () => [
                'own' => $this->user_id == $viewer->id,
                'saved' => $this->user_id != $viewer->id && $this->resource->isSavedBy($viewer),
            ]),
        ];
    }
}
