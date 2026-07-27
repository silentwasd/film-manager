<?php

namespace App\Http\Controllers\Public;

use App\Enums\CollectionVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\CollectionResource;
use App\Models\Collection;
use App\Models\User;

class UserCollectionController extends Controller
{
    /**
     * Вложенный адрес — только для личных коллекций: у публичной единственный
     * адрес короткий, поэтому здесь она отдаёт 404. Коллекция обязана
     * принадлежать пользователю из пути, иначе тоже 404.
     */
    public function show(string $userKey, string $key)
    {
        $userId = User::idFromPublicKey($userKey);
        $id = Collection::idFromPublicKey($key);

        if ($userId === null || $id === null) {
            abort(404);
        }

        $collection = Collection::query()
            ->where('visibility', CollectionVisibility::Personal)
            ->where('user_id', $userId)
            ->withCount('films')
            ->with([
                'user',
                'films' => fn ($q) => $q->with('film')->orderBy('position'),
            ])
            ->findOrFail($id);

        return new CollectionResource($collection);
    }
}
