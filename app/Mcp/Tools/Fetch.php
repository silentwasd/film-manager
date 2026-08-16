<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Presenter;
use App\Models\Collection;
use App\Models\Film;
use App\Models\FilmPerson;
use App\Models\Person;
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

/**
 * Вторая половина контракта коннекторов ChatGPT: получает id из search
 * и отдаёт документ сплошным текстом.
 */
#[Name('fetch')]
#[Title('Полный документ по id из поиска')]
#[Description(
    'Возвращает содержимое элемента каталога по идентификатору из результатов search '.
    '(вида "film:123", "person:45", "collection:7") — текстом, пригодным для цитирования.'
)]
#[IsReadOnly]
#[IsIdempotent]
class Fetch extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'id' => 'required|string|max:64',
        ]);

        [$type, $id] = array_pad(explode(':', $data['id'], 2), 2, null);

        if (! is_numeric($id)) {
            return Response::error('Идентификатор должен выглядеть как "film:123", "person:45" или "collection:7".');
        }

        return match ($type) {
            'film' => $this->film((int) $id),
            'person' => $this->person((int) $id),
            'collection' => $this->collection((int) $id),
            default => Response::error("Неизвестный тип «{$type}». Допустимы film, person, collection."),
        };
    }

    private function film(int $id): Response
    {
        $film = Film::query()
            ->with([
                'genres', 'countries', 'tags', 'companies',
                'people' => fn (HasMany $has) => $has->orderBy('order_id')->orderBy('id'),
                'people.person',
            ])
            ->find($id);

        if ($film === null) {
            return Response::error("Фильма с id {$id} в каталоге нет.");
        }

        $lines = array_filter([
            $film->name.($film->original_name ? ' / '.$film->original_name : ''),
            $film->produced_year ? 'Год: '.$film->produced_year : null,
            'Формат: '.$film->format?->value,
            $film->genres->isNotEmpty() ? 'Жанры: '.$film->genres->pluck('name')->join(', ') : null,
            $film->countries->isNotEmpty() ? 'Страны: '.$film->countries->pluck('name')->join(', ') : null,
            $film->companies->isNotEmpty() ? 'Студии: '.$film->companies->pluck('name')->join(', ') : null,
            $film->tags->isNotEmpty() ? 'Теги: '.$film->tags->pluck('name')->join(', ') : null,
            $this->peopleLine($film),
            $film->description ? "\n".$film->description : null,
        ]);

        return Response::json([
            'id' => 'film:'.$film->id,
            'title' => $film->name,
            'text' => implode("\n", $lines),
            'url' => Presenter::filmUrl($film->id),
            'metadata' => [
                'year' => $film->produced_year,
                'format' => $film->format?->value,
                'cover' => Presenter::image($film->cover),
            ],
        ]);
    }

    private function peopleLine(Film $film): ?string
    {
        if ($film->people->isEmpty()) {
            return null;
        }

        return collect(Presenter::filmPeople($film, 8))
            ->map(fn (array $people, string $role) => $role.': '.collect($people)->pluck('name')->join(', '))
            ->join("\n");
    }

    private function person(int $id): Response
    {
        $person = Person::query()->with('country')->find($id);

        if ($person === null) {
            return Response::error("Человека с id {$id} в каталоге нет.");
        }

        $films = $person->films()
            ->with('film')
            ->get()
            ->sortByDesc(fn (FilmPerson $filmPerson) => $filmPerson->film?->produced_year ?? 0)
            ->take(40)
            ->map(fn (FilmPerson $filmPerson) => trim(sprintf(
                '%s (%s) — %s%s',
                $filmPerson->film?->name ?? '—',
                $filmPerson->film?->produced_year ?? '—',
                $filmPerson->role?->value ?? '',
                $filmPerson->role_details ? ', '.$filmPerson->role_details : ''
            )));

        $lines = array_filter([
            $person->name.($person->original_name ? ' / '.$person->original_name : ''),
            $person->birth_date ? 'Родился: '.$person->birth_date->format('Y-m-d') : null,
            $person->death_date ? 'Умер: '.$person->death_date->format('Y-m-d') : null,
            $person->country?->name ? 'Страна: '.$person->country->name : null,
            $films->isNotEmpty() ? "\nФильмография:\n".$films->join("\n") : null,
        ]);

        return Response::json([
            'id' => 'person:'.$person->id,
            'title' => $person->name,
            'text' => implode("\n", $lines),
            'url' => Presenter::personUrl($person->id),
            'metadata' => [
                'photo' => Presenter::image($person->photo),
            ],
        ]);
    }

    private function collection(int $id): Response
    {
        $collection = Collection::query()
            ->visible()
            ->with(['user', 'films' => fn ($query) => $query->with('film')->orderBy('position')])
            ->find($id);

        if ($collection === null) {
            return Response::error("Подборки с id {$id} нет или она скрыта.");
        }

        $films = $collection->films->map(fn ($item) => trim(sprintf(
            '%d. %s (%s)%s',
            $item->position,
            $item->film?->name ?? '—',
            $item->film?->produced_year ?? '—',
            $item->note ? ' — '.$item->note : ''
        )));

        $lines = array_filter([
            $collection->name,
            $collection->user?->name ? 'Автор: '.$collection->user->name : null,
            $collection->description,
            $films->isNotEmpty() ? "\n".$films->join("\n") : null,
        ]);

        return Response::json([
            'id' => 'collection:'.$collection->id,
            'title' => $collection->name,
            'text' => implode("\n", $lines),
            'url' => $collection->publicUrl(),
            'metadata' => [
                'visibility' => $collection->visibility?->value,
                'films_count' => $collection->films->count(),
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Идентификатор из результатов search: "film:123", "person:45" или "collection:7".')
                ->required(),
        ];
    }
}
