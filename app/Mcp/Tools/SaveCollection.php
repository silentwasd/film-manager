<?php

namespace App\Mcp\Tools;

use App\Enums\CollectionVisibility;
use App\Mcp\Support\Presenter;
use App\Models\Collection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('save_collection')]
#[Title('Создать или изменить подборку')]
#[Description(
    'Без id создаёт новую подборку пользователя, с id — переименовывает существующую '.
    'или меняет её описание и видимость. Видимость: hidden — только автору (так '.
    'создаётся по умолчанию), personal — по ссылке из профиля, public — в общем '.
    'списке подборок сайта. Публикация делает подборку видимой всем: спросить пользователя.'
)]
class SaveCollection extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'id' => 'nullable|integer|min:1',
            'name' => 'required_without:id|nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'visibility' => ['nullable', 'string', 'in:'.implode(',', array_column(CollectionVisibility::cases(), 'value'))],
        ]);

        $userId = $request->user()->getAuthIdentifier();

        if ($id = $data['id'] ?? null) {
            $collection = Collection::query()->where('user_id', $userId)->find($id);

            if ($collection === null) {
                return Response::error("Подборки с id {$id} у вас нет.");
            }
        } else {
            $collection = new Collection(['user_id' => $userId]);
        }

        $collection->fill(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? null,
        ], fn ($value) => $value !== null));

        $collection->user_id = $userId;
        $collection->save();
        $collection->load('user')->loadCount('films');

        return Response::json([
            'saved' => true,
            ...Presenter::collectionCard($collection),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Идентификатор существующей подборки. Не указан — создаётся новая.'),
            'name' => $schema->string()->description('Название подборки. Обязательно при создании.'),
            'description' => $schema->string()->description('Описание подборки.'),
            'visibility' => $schema->string()
                ->enum(array_column(CollectionVisibility::cases(), 'value'))
                ->description('hidden — только автору, personal — по ссылке из профиля, public — публично.'),
        ];
    }
}
