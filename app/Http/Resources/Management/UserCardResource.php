<?php

namespace App\Http\Resources\Management;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Пользователь в списках друзей и участников плана: только то, что и так
 * видно в публичном профиле. Без email и роли.
 *
 * @mixin User
 */
class UserCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_key' => $this->public_key,
        ];
    }
}
