<?php

namespace App\Mcp\Tools;

use App\Models\Feedback;
use App\Models\Film;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('set_film_reaction')]
#[Title('Оценить фильм')]
#[Description(
    'Ставит оценку фильму от лица пользователя: reaction 1 — понравилось, '.
    '-1 — не понравилось, 0 — нейтрально, ни за ни против. Ноль это именно '.
    'оценка, а не её отсутствие; правда, если нейтральную оценку ставят без '.
    'отзыва, запись удаляется — так же ведёт себя кнопка на сайте. '.
    'Вместе с оценкой можно оставить публичный отзыв до 512 символов. '.
    'У сериала с указанным числом сезонов (seasons в get_film) можно оценить '.
    'отдельный сезон параметром season; без него оценка ставится сериалу '.
    'целиком. Оценки сезонов и общая независимы — у каждого своя запись. '.
    'Отзыв виден на сайте всем — писать только то, что пользователь сам '.
    'продиктовал, и подтвердить у него текст.'
)]
#[IsIdempotent]
class SetFilmReaction extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'film_id' => 'required|integer|exists:films,id',
            'reaction' => 'required|integer|min:-1|max:1',
            'review' => 'nullable|string|max:512',
            'season' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $film = Film::query()->findOrFail($data['film_id']);
        $review = $data['review'] ?? null;
        $season = $data['season'] ?? null;

        if ($season !== null && (! $film->format?->isSerial() || ! $film->seasons)) {
            return Response::error("У «{$film->name}» не указаны сезоны — оценить можно только тайтл целиком.");
        }

        if ($season !== null && $season > $film->seasons) {
            return Response::error("У «{$film->name}» сезонов: {$film->seasons}, сезона {$season} нет.");
        }

        // Нейтральная оценка без отзыва хранить нечего: сайт в этом случае
        // удаляет запись, и повторять его поведение здесь обязательно —
        // иначе список «оценённого» разъедется между фронтом и коннектором.
        if ($data['reaction'] === 0 && $review === null) {
            $removed = Feedback::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('film_id', $film->id)
                ->where('season', $season)
                ->whereNull('text')
                ->delete();

            if ($removed > 0) {
                return Response::json(array_filter([
                    'saved' => true,
                    'film_id' => $film->id,
                    'season' => $season,
                ], fn ($value) => $value !== null) + ['reaction' => null]);
            }
        }

        $feedback = Feedback::query()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier(), 'film_id' => $film->id, 'season' => $season],
            array_filter([
                'reaction' => $data['reaction'],
                'text' => $review,
            ], fn ($value) => $value !== null),
        );

        return Response::json([
            'saved' => true,
            'film_id' => $film->id,
            'film' => $film->name,
            ...($season !== null ? ['season' => $season] : []),
            'reaction' => $feedback->reaction,
            'review' => $feedback->text,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'film_id' => $schema->integer()
                ->description('Идентификатор фильма.')
                ->required(),
            'reaction' => $schema->integer()
                ->enum([-1, 0, 1])
                ->description('1 — понравилось, -1 — не понравилось, 0 — нейтрально. Ноль без отзыва снимает ранее поставленную оценку.')
                ->required(),
            'review' => $schema->string()
                ->description('Публичный отзыв до 512 символов. Виден всем посетителям сайта.'),
            'season' => $schema->integer()
                ->description('Номер сезона сериала, от 1 до seasons из get_film. Не указан — оценка сериала целиком.'),
        ];
    }
}
