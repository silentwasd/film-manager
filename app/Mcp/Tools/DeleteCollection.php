<?php

namespace App\Mcp\Tools;

use App\Models\Collection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('delete_collection')]
#[Title('Удалить подборку')]
#[Description(
    'Удаляет свою подборку вместе с её составом. Сами фильмы каталога остаются. '.
    'Отменить нельзя — обязательно подтвердить у пользователя.'
)]
#[IsDestructive]
#[IsIdempotent]
class DeleteCollection extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $collection = Collection::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->find($data['id']);

        if ($collection === null) {
            return Response::error("Подборки с id {$data['id']} у вас нет.");
        }

        $name = $collection->name;
        $collection->films()->delete();
        $collection->delete();

        return Response::json(['removed' => true, 'id' => $data['id'], 'name' => $name]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Идентификатор своей подборки.')
                ->required(),
        ];
    }
}
