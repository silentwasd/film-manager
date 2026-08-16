<?php

namespace App\Mcp\Tools;

use App\Enums\FilmWatchStatus;
use App\Mcp\Support\Presenter;
use App\Models\Film;
use App\Models\FilmWatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('set_watch_status')]
#[Title('Отметить статус просмотра')]
#[Description(
    'Ставит или меняет статус фильма в списке пользователя: watched, to-watch, '.
    'must-finish, dropped. Если фильма в списке не было, он добавляется. '.
    'Меняет данные пользователя — подтвердить у него перед вызовом.'
)]
#[IsIdempotent]
class SetWatchStatus extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'film_id' => 'required|integer|exists:films,id',
            'status' => ['required', 'string', 'in:'.implode(',', array_column(FilmWatchStatus::cases(), 'value'))],
        ]);

        $user = $request->user();
        $film = Film::query()->findOrFail($data['film_id']);

        $watcher = FilmWatcher::query()->updateOrCreate(
            ['watcher_id' => $user->getAuthIdentifier(), 'film_id' => $film->id],
            ['status' => $data['status']],
        );

        $watcher->setRelation('film', $film);

        return Response::json([
            'saved' => true,
            ...Presenter::watcher($watcher),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'film_id' => $schema->integer()
                ->description('Идентификатор фильма из search_films.')
                ->required(),
            'status' => $schema->string()
                ->enum(array_column(FilmWatchStatus::cases(), 'value'))
                ->description('watched — просмотрено, to-watch — хочу посмотреть, must-finish — нужно досмотреть, dropped — брошено.')
                ->required(),
        ];
    }
}
