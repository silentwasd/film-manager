<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\Request;

/**
 * Чужая коллекция в своём разделе «Коллекции». Это закладка: править её
 * нельзя, а удаление убирает её только из своего списка, у автора всё цело.
 */
class CollectionSaveController extends Controller
{
    public function store(Request $request, Collection $collection)
    {
        $user = $request->user();

        if ($collection->user_id == $user->id) {
            abort(422, 'Это ваша коллекция — она и так в вашем списке.');
        }

        // Скрытая для всех, кроме автора, — та же 404, что и на публичных адресах.
        if ($user->cannot('save', $collection)) {
            abort(404);
        }

        $user->savedCollections()->syncWithoutDetaching([$collection->id]);

        return response()->json(['saved' => true]);
    }

    public function destroy(Request $request, Collection $collection)
    {
        $request->user()->savedCollections()->detach($collection->id);

        return response()->json(['saved' => false]);
    }
}
