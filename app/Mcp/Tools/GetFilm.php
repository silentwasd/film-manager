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
    'Дополнительно отдаёт статус просмотра и заметки текущего пользователя. '.
    'У сериалов есть seasons — число сезонов; my.reaction и my.review — оценка '.
    'сериала целиком, а my.seasons — оценки и отзывы на отдельные сезоны.'
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
                'genres', 'countries', 'tags', 'companies', 'franchises',
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

            // Отдельно от array_filter выше: null здесь значимый ответ —
            // «не оценивал», и его нельзя путать с нейтральной оценкой 0.
            $result['my']['reaction'] = $feedback === null ? null : (int) $feedback->reaction;

            // Отзывы на отдельные сезоны — рядом с общей оценкой, а не вместо неё.
            $seasons = $film->allFeedbacks()
                ->where('user_id', $user->getAuthIdentifier())
                ->whereNotNull('season')
                ->orderBy('season')
                ->get()
                ->map(fn ($item) => array_filter([
                    'season' => $item->season,
                    'reaction' => (int) $item->reaction,
                    'review' => $item->text,
                ], fn ($value) => $value !== null))
                ->all();

            if ($seasons) {
                $result['my']['seasons'] = $seasons;
            }
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
