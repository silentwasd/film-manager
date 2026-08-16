<?php

namespace App\Mcp\Tools;

use App\Models\Film;
use App\Models\Rating;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('add_film_note')]
#[Title('Личная заметка к фильму')]
#[Description(
    'Добавляет личную заметку к фильму — то, что на сайте называется рейтингом. '.
    'В отличие от отзыва из set_film_reaction, заметка видна только автору, '.
    'и их у одного фильма может быть несколько. Уже сохранённые заметки '.
    'возвращает get_film в поле my.notes.'
)]
class AddFilmNote extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'film_id' => 'required|integer|exists:films,id',
            'note' => 'required|string|max:2000',
        ]);

        $film = Film::query()->findOrFail($data['film_id']);

        $rating = Rating::query()->create([
            'user_id' => $request->user()->getAuthIdentifier(),
            'film_id' => $film->id,
            'data' => ['comment' => $data['note']],
        ]);

        return Response::json([
            'saved' => true,
            'note_id' => $rating->id,
            'film_id' => $film->id,
            'film' => $film->name,
            'note' => $data['note'],
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
            'note' => $schema->string()
                ->description('Текст заметки. Виден только самому пользователю.')
                ->required(),
        ];
    }
}
