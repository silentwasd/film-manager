<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Presenter;
use App\Models\Film;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_film')]
#[Title('Карточка фильма')]
#[Description(
    'Полная карточка фильма по id: описание, жанры, страны, теги, студии, '.
    'участники по ролям, ссылки на постер и на страницу сайта. '.
    'Дополнительно отдаёт статус просмотра и заметки текущего пользователя.'
)]
#[IsReadOnly]
#[IsIdempotent]
class GetFilm extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $film = Film::query()
            ->with([
                'genres', 'countries', 'tags', 'companies',
                // Тот же порядок ролей, что и на сайте: сначала режиссёр,
                // потом актёры, дальше остальные.
                'people' => fn (HasMany $has) => $has
                    ->orderByRaw("FIELD(role, 'director', 'actor', 'voice-actor', 'producer', 'dubbing-director', 'translator', 'dubbing-actor', 'screenwriter', 'operator', 'composer', 'sound-director', 'artist', 'editor')")
                    ->orderBy('order_id')
                    ->orderBy('id'),
                'people.person',
            ])
            ->find($data['id']);

        if ($film === null) {
            return Response::error("Фильма с id {$data['id']} в каталоге нет.");
        }

        $result = Presenter::filmDetail($film);

        if ($user = $request->user()) {
            $watcher = $film->watchers()->where('watcher_id', $user->getAuthIdentifier())->first();
            $feedback = $film->feedbacks()->where('user_id', $user->getAuthIdentifier())->first();

            $result['my'] = array_filter([
                'watch_status' => $watcher?->status,
                'reaction' => $feedback?->reaction,
                'review' => $feedback?->text,
                'notes' => $film->ratings()
                    ->where('user_id', $user->getAuthIdentifier())
                    ->latest()
                    ->get()
                    ->map(fn ($rating) => [
                        'id' => $rating->id,
                        'note' => $rating->data['comment'] ?? null,
                    ])
                    ->filter(fn (array $note) => $note['note'] !== null)
                    ->values()
                    ->all() ?: null,
            ], fn ($value) => $value !== null);
        }

        return Response::json($result);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Идентификатор фильма из выдачи search_films.')
                ->required(),
        ];
    }
}
