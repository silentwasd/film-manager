<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Presenter;
use App\Models\Collection;
use App\Models\Film;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('manage_collection_films')]
#[Title('Состав подборки: добавить и убрать')]
#[Description(
    'Меняет состав своей подборки: action=add добавляет фильмы в конец (или на '.
    'позицию position), action=remove убирает их, action=note правит подпись '.
    'к фильму внутри подборки. Порядок нумеруется с единицы и после удаления '.
    'пересчитывается. Работает только со своими подборками.'
)]
class ManageCollectionFilms extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'collection_id' => 'required|integer|min:1',
            'action' => 'required|string|in:add,remove,note',
            'film_ids' => 'required|array|min:1|max:50',
            'film_ids.*' => 'integer|min:1',
            'note' => 'nullable|string|max:512',
            'position' => 'nullable|integer|min:1',
        ]);

        $userId = $request->user()->getAuthIdentifier();

        $collection = Collection::query()->where('user_id', $userId)->find($data['collection_id']);

        if ($collection === null) {
            return Response::error("Подборки с id {$data['collection_id']} у вас нет.");
        }

        $filmIds = array_values(array_unique($data['film_ids']));
        $known = Film::query()->whereIn('id', $filmIds)->pluck('id')->all();
        $missing = array_values(array_diff($filmIds, $known));

        $result = DB::transaction(fn () => match ($data['action']) {
            'add' => $this->add($collection, $known, $data['note'] ?? null, $data['position'] ?? null),
            'remove' => $this->remove($collection, $known),
            'note' => $this->note($collection, $known, $data['note'] ?? null),
        });

        $collection->load(['user', 'films' => fn ($query) => $query->with('film')->orderBy('position')]);

        return Response::json(array_filter([
            'action' => $data['action'],
            'affected' => $result,
            'unknown_film_ids' => $missing === [] ? null : $missing,
            ...Presenter::collectionDetail($collection),
        ], fn ($value) => $value !== null));
    }

    /**
     * @param  array<int, int>  $filmIds
     */
    private function add(Collection $collection, array $filmIds, ?string $note, ?int $position): int
    {
        $existing = $collection->films()->pluck('film_id')->all();
        $filmIds = array_values(array_diff($filmIds, $existing));

        if ($filmIds === []) {
            return 0;
        }

        $max = (int) ($collection->films()->max('position') ?? 0);
        $position = $position === null ? $max + 1 : min($position, $max + 1);

        // Вставка в середину: всё, что ниже, съезжает вниз на число новых фильмов.
        if ($position <= $max) {
            $collection->films()->where('position', '>=', $position)->increment('position', count($filmIds));
        }

        foreach ($filmIds as $index => $filmId) {
            $collection->films()->create([
                'film_id' => $filmId,
                'position' => $position + $index,
                'note' => $note,
            ]);
        }

        return count($filmIds);
    }

    /**
     * @param  array<int, int>  $filmIds
     */
    private function remove(Collection $collection, array $filmIds): int
    {
        $removed = $collection->films()->whereIn('film_id', $filmIds)->delete();

        if ($removed > 0) {
            $collection->films()
                ->orderBy('position')
                ->get()
                ->each(fn ($item, $index) => $item->update(['position' => $index + 1]));
        }

        return $removed;
    }

    /**
     * @param  array<int, int>  $filmIds
     */
    private function note(Collection $collection, array $filmIds, ?string $note): int
    {
        return $collection->films()->whereIn('film_id', $filmIds)->update(['note' => $note]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'collection_id' => $schema->integer()
                ->description('Идентификатор своей подборки.')
                ->required(),
            'action' => $schema->string()
                ->enum(['add', 'remove', 'note'])
                ->description('add — добавить фильмы, remove — убрать, note — заменить подпись.')
                ->required(),
            'film_ids' => $schema->array()->items($schema->integer())
                ->description('Идентификаторы фильмов, до 50 за раз.')
                ->required(),
            'note' => $schema->string()
                ->description('Подпись к фильму внутри подборки, до 512 символов. Для action=note пустая строка стирает подпись.'),
            'position' => $schema->integer()
                ->description('Только для add: с какой позиции вставить. По умолчанию в конец.'),
        ];
    }
}
