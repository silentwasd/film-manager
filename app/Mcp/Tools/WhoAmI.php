<?php

namespace App\Mcp\Tools;

use App\Models\Collection;
use App\Models\FilmWatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('whoami')]
#[Title('Чей это аккаунт')]
#[Description(
    'Показывает, от чьего имени подключён коннектор, и сводку по списку просмотра. '.
    'Полезно вызвать первым, чтобы понимать, чьи данные правятся.'
)]
#[IsReadOnly]
#[IsIdempotent]
class WhoAmI extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();
        $id = $user->getAuthIdentifier();

        return Response::json([
            'id' => $id,
            'name' => $user->name,
            'catalog_url' => rtrim((string) config('app.frontend_url'), '/'),
            'watchlist' => FilmWatcher::query()
                ->where('watcher_id', $id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
            'collections' => Collection::query()->where('user_id', $id)->count(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
