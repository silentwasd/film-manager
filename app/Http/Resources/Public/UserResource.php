<?php

namespace App\Http\Resources\Public;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичный профиль. Намеренно без email и role — наружу уходят только
 * имя, ключ страницы и список видимых коллекций.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_key' => $this->public_key,
            'collections' => CollectionCardResource::collection($this->whenLoaded('collections')),
        ];
    }
}
