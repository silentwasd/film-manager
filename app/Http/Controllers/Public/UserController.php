<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\UserResource;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Публичный профиль: имя автора и его коллекции уровней public и personal.
     * Скрытые коллекции сюда не попадают даже для самого владельца — профиль
     * показывает ровно то, что видят посторонние.
     */
    public function show(string $key)
    {
        $id = User::idFromPublicKey($key);

        if ($id === null) {
            abort(404);
        }

        $user = User::query()
            ->with([
                'collections' => fn ($q) => $q->visible()
                    ->withCount('films')
                    ->orderByDesc('updated_at'),
            ])
            ->findOrFail($id);

        // Автор у всех этих коллекций один и уже загружен — проставляем связь
        // вручную, чтобы publicPath() собрал вложенный путь без лишних запросов.
        $user->collections->each->setRelation('user', $user);

        return new UserResource($user);
    }
}
