<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Presenter;
use App\Models\Person;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_people')]
#[Title('Поиск людей')]
#[Description(
    'Ищет людей каталога — режиссёров, актёров, композиторов, сэйю — по имени. '.
    'Однофамильцев и тёзок в базе много, поэтому выбирать нужного стоит по '.
    'фильмографии из get_person.'
)]
#[IsReadOnly]
#[IsIdempotent]
class SearchPeople extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'query' => 'required|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $paginator = Person::query()
            ->where(fn (Builder $where) => $where
                ->where('name', 'LIKE', '%'.$data['query'].'%')
                ->orWhere('original_name', 'LIKE', '%'.$data['query'].'%')
            )
            ->withCount('films')
            // Сначала те, у кого в каталоге больше работ: тёзка с одной ролью
            // почти никогда не тот, кого спрашивают.
            ->orderByDesc('films_count')
            ->orderBy('id')
            ->paginate(perPage: $data['per_page'] ?? 20, page: $data['page'] ?? 1);

        return Response::json(Presenter::page($paginator, fn (Person $person) => [
            ...Presenter::personCard($person),
            'films_count' => $person->films_count,
        ]));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Часть имени — русского или оригинального.')
                ->required(),
            'page' => $schema->integer()->description('Номер страницы, с единицы.'),
            'per_page' => $schema->integer()->description('Размер страницы, до 50. По умолчанию 20.'),
        ];
    }
}
