<?php

namespace App\Mcp\Tools;

use App\Enums\CollectionVisibility;
use App\Mcp\Support\Presenter;
use App\Models\Collection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_collections')]
#[Title('Подборки')]
#[Description(
    'Список подборок фильмов. scope=mine — свои, включая скрытые; '.
    'scope=public — публичные подборки всех пользователей. Состав подборки '.
    'отдаёт get_collection.'
)]
#[IsReadOnly]
#[IsIdempotent]
class ListCollections extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'scope' => 'nullable|string|in:mine,public',
            'query' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $userId = $request->user()->getAuthIdentifier();
        $scope = $data['scope'] ?? 'mine';

        $paginator = Collection::query()
            ->with('user')
            ->withCount('films')
            ->when($scope === 'mine', fn (Builder $query) => $query->where('user_id', $userId))
            ->when($scope === 'public', fn (Builder $query) => $query->where('visibility', CollectionVisibility::Public))
            ->when($data['query'] ?? null, fn (Builder $query, string $name) => $query->where('name', 'LIKE', '%'.$name.'%'))
            ->orderByDesc('id')
            ->paginate(perPage: $data['per_page'] ?? 25, page: $data['page'] ?? 1);

        return Response::json(Presenter::page(
            $paginator,
            fn (Collection $collection) => Presenter::collectionCard($collection)
        ));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema->string()
                ->enum(['mine', 'public'])
                ->description('mine — подборки пользователя (по умолчанию), public — чужие публичные.'),
            'query' => $schema->string()->description('Часть названия подборки.'),
            'page' => $schema->integer()->description('Номер страницы, с единицы.'),
            'per_page' => $schema->integer()->description('Размер страницы, до 50. По умолчанию 25.'),
        ];
    }
}
