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
    '-1 — не понравилось, 0 — снять оценку. Вместе с оценкой можно оставить '.
    'публичный отзыв до 512 символов. Отзыв виден на сайте всем — писать только '.
    'то, что пользователь сам продиктовал, и подтвердить у него текст.'
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
        ]);

        $user = $request->user();
        $film = Film::query()->findOrFail($data['film_id']);
        $review = $data['review'] ?? null;

        // Пустая оценка без текста — это снятие реакции, а не запись нуля:
        // так же ведёт себя кнопка на сайте.
        if ($data['reaction'] === 0 && $review === null) {
            $removed = Feedback::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('film_id', $film->id)
                ->whereNull('text')
                ->delete();

            if ($removed > 0) {
                return Response::json(['saved' => true, 'film_id' => $film->id, 'reaction' => null]);
            }
        }

        $feedback = Feedback::query()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier(), 'film_id' => $film->id],
            array_filter([
                'reaction' => $data['reaction'],
                'text' => $review,
            ], fn ($value) => $value !== null),
        );

        return Response::json([
            'saved' => true,
            'film_id' => $film->id,
            'film' => $film->name,
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
                ->description('1 — понравилось, -1 — не понравилось, 0 — снять оценку.')
                ->required(),
            'review' => $schema->string()
                ->description('Публичный отзыв до 512 символов. Виден всем посетителям сайта.'),
        ];
    }
}
