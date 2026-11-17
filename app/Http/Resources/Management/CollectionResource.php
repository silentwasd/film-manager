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
            // Чужая коллекция в списке — добавленная к себе. Автор нужен,
            // чтобы подписать строку; у своих он не отдаётся.
            'is_own' => $this->user_id == auth()->id(),
            'author' => $this->when(
                $this->user_id != auth()->id() && $this->relationLoaded('user'),
                fn () => [
                    'name' => $this->user->name,
                    'public_key' => $this->user->public_key,
                ]
            ),
        ];
    }
}
