<?php

namespace App\Mcp\Tools;

use App\Models\Rating;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('delete_film_note')]
#[Title('Удалить заметку к фильму')]
#[Description('Удаляет личную заметку по её note_id из get_film. Восстановить нельзя — спросить пользователя.')]
#[IsDestructive]
#[IsIdempotent]
class DeleteFilmNote extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'note_id' => 'required|integer|min:1',
        ]);

        $deleted = Rating::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('id', $data['note_id'])
            ->delete();

        if ($deleted === 0) {
            return Response::error("Заметки с id {$data['note_id']} у вас нет.");
        }

        return Response::json(['removed' => true, 'note_id' => $data['note_id']]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'note_id' => $schema->integer()
                ->description('Идентификатор заметки из get_film → my.notes[].id.')
                ->required(),
        ];
    }
}
