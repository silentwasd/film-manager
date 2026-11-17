<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Presenter;
use App\Models\Collection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * То же, что кнопка «Добавить к себе» / «Убрать из моих» на сайте
 * (Management\CollectionSaveController): закладка на чужую подборку.
 */
#[Name('bookmark_collection')]
#[Title('Добавить чужую подборку к себе')]
#[Description(
    'action=add добавляет чужую публичную или личную подборку в раздел «Коллекции» '.
    'пользователя, action=remove убирает её оттуда. Это закладка, а не копия: '.
    'состав и название остаются у автора, править её нельзя, remove ничего не удаляет '.
    'у автора. Свою подборку добавить нельзя — она и так в списке.'
)]
#[IsIdempotent]
class BookmarkCollection extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'id' => 'required|integer|min:1',
            'action' => 'required|string|in:add,remove',
        ]);

        $user = $request->user();
        $collection = Collection::query()->with('user')->withCount('films')->find($data['id']);

        if ($data['action'] === 'remove') {
            $removed = $user->savedCollections()->detach($data['id']) > 0;

            return Response::json([
                'removed' => $removed,
                'id' => $data['id'],
                ...($collection ? ['name' => $collection->name] : []),
            ]);
        }

        // Скрытая чужая неотличима от несуществующей — как на сайте.
        if ($collection === null || ($collection->user_id != $user->id && $user->cannot('save', $collection))) {
            return Response::error("Подборки с id {$data['id']} нет или она недоступна.");
        }

        if ($collection->user_id == $user->id) {
            return Response::error("Подборка {$data['id']} — ваша собственная, она и так в вашем списке.");
        }

        $user->savedCollections()->syncWithoutDetaching([$collection->id]);

        return Response::json([
            'added' => true,
            ...Presenter::collectionCard($collection),
            'is_mine' => false,
            'saved' => true,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Идентификатор чужой подборки из list_collections или get_collection.')
                ->required(),
            'action' => $schema->string()
                ->enum(['add', 'remove'])
                ->description('add — добавить к себе, remove — убрать из своих.')
                ->required(),
        ];
    }
}
