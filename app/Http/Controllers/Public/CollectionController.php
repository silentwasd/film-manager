<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\CollectionResource;
use App\Models\Collection;

class CollectionController extends Controller
{
    /**
     * Короткий адрес — только для публичных коллекций. Личная сюда не попадает
     * даже по верному ключу: у неё единственный адрес внутри профиля автора.
     *
     * $key — ключ вида "12-luchshie-boeviki". Поиск идёт только по ведущему id,
     * слаг декоративный, поэтому устаревший слаг всё равно откроет страницу.
     */
    public function show(string $key)
    {
        $id = Collection::idFromPublicKey($key);

        if ($id === null) {
            abort(404);
        }

        $collection = Collection::query()
            ->public()
            ->withCount('films')
            ->with([
                'user',
                'films' => fn ($q) => $q->with('film')->orderBy('position'),
            ])
            ->findOrFail($id);

        return new CollectionResource($collection);
    }
}
