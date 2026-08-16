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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_collection')]
#[Title('Состав подборки')]
#[Description('Подборка по id со списком фильмов в заданном порядке и заметками к ним.')]
#[IsReadOnly]
#[IsIdempotent]
class GetCollection extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $collection = Collection::query()
            ->with(['user', 'films' => fn ($query) => $query->with('film')->orderBy('position')])
            ->find($data['id']);

        // Скрытая подборка видна только владельцу — как и на сайте.
        if ($collection === null || (
            $collection->visibility === CollectionVisibility::Hidden &&
            $collection->user_id !== $request->user()->getAuthIdentifier()
        )) {
            return Response::error("Подборки с id {$data['id']} нет или она недоступна.");
        }

        return Response::json([
            ...Presenter::collectionDetail($collection),
            'is_mine' => $collection->user_id === $request->user()->getAuthIdentifier(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Идентификатор подборки из list_collections.')
                ->required(),
        ];
    }
}
