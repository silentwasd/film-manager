<?php

namespace App\Services\Shikimori;

use App\Enums\FilmModerationStatus;
use App\Enums\PersonRole;
use App\Models\Company;
use App\Models\Country;
use App\Models\Film;
use App\Models\Genre;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Собирает карточку фильма по данным конкретного аниме с Шикимори.
 *
 * Один вызов import() = 3 + N запросов к API, где N — число персонажей,
 * у которых тянем сэйю (у сэйю нет группового эндпоинта, только по персонажу).
 */
class ShikimoriImporter
{
    /**
     * Верхняя граница на персонажей: у длинных тайтлов их бывает под сотню,
     * и каждый — отдельный запрос к API. Главные роли берём первыми.
     */
    protected const int MAX_CHARACTERS = 40;

    protected array $log = [];

    public function __construct(
        protected ShikimoriClient $client
    )
    {
    }

    /**
     * @param int       $shikimoriId ID аниме на Шикимори
     * @param User      $author      кому записать авторство карточки
     * @param bool      $withPeople  тянуть ли персонал
     * @param bool      $withSeyu    тянуть ли озвучку (дорого по запросам)
     *
     * @return array{film: Film|null, log: array<string>}
     */
    public function import(int $shikimoriId, User $author, bool $withPeople = true, bool $withSeyu = true): array
    {
        $this->log = [];

        $anime = $this->client->anime($shikimoriId);

        if (!$anime)
            return ['film' => null, 'log' => ["Аниме {$shikimoriId} на Шикимори не найдено."]];

        if ($existing = Film::where('shikimori_id', $shikimoriId)->first()) {
            return [
                'film' => $existing,
                'log'  => ["Аниме уже импортировано — карточка #{$existing->id} «{$existing->name}». Повторный импорт пропущен."]
            ];
        }

        // Картинки и роли тянем ДО транзакции: сеть внутри транзакции
        // держала бы блокировки на всё время выкачивания.
        $cover      = $this->fetchImage($anime['image']['original'] ?? null, 'films');
        $background = $this->fetchBackground($shikimoriId);
        $roles      = $withPeople ? $this->client->roles($shikimoriId) : [];
        $people     = $withPeople ? $this->collectPeople($roles, $withSeyu) : [];
        $photos     = $this->fetchPersonPhotos($people);

        $film = DB::transaction(function () use ($anime, $shikimoriId, $author, $cover, $background, $people, $photos) {
            $film = Film::create([
                'name'              => $anime['russian'] ?: $anime['name'],
                'original_name'     => $anime['name'],
                'shikimori_id'      => $shikimoriId,
                'format'            => ShikimoriMap::format($anime['kind'] ?? null, $anime['episodes'] ?? null),
                'description'       => ShikimoriMap::description($anime['description'] ?? null),
                'release_date'      => $anime['aired_on'] ?? null,
                'produced_year'     => $anime['aired_on'] ? (int)substr($anime['aired_on'], 0, 4) : null,
                'cover'             => $cover,
                'background_cover'  => $background,
                'author_id'         => $author->id,
                'moderation_status' => FilmModerationStatus::Draft
            ]);

            $film->genres()->sync($this->genreIds($anime['genres'] ?? []));
            $film->countries()->sync($this->countryIds());
            $film->companies()->sync($this->companyIds($anime['studios'] ?? [], $author));

            $this->attachPeople($film, $people, $photos, $author);

            return $film;
        });

        $this->note("Карточка #{$film->id} «{$film->name}» создана в статусе «черновик».");

        return ['film' => $film, 'log' => $this->log];
    }

    /**
     * Жанры Шикимори специфичны для аниме (Сёнен, Сёдзё-ай, Меха, Школа) и
     * с киношным справочником пересекаются лишь частично. Недостающие заводим.
     */
    protected function genreIds(array $genres): array
    {
        $ids = [];

        foreach ($genres as $genre) {
            $name = $genre['russian'] ?: $genre['name'];

            if (!$name)
                continue;

            $model = Genre::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if (!$model) {
                $model = Genre::create([
                    'name' => $name,
                    'slug' => ShikimoriMap::slug($genre['name'] ?: $name)
                ]);

                $this->note("Заведён новый жанр «{$name}».");
            }

            $ids[] = $model->id;
        }

        return $ids;
    }

    /**
     * Шикимори не отдаёт страну производства. Каталог у него японоцентричный,
     * но там есть и китайские/корейские тайтлы — их страну придётся поправить руками.
     */
    protected function countryIds(): array
    {
        $country = Country::whereRaw('LOWER(name) = ?', ['япония'])->first()
            ?? Country::create(['name' => 'Япония']);

        return [$country->id];
    }

    protected function companyIds(array $studios, User $author): array
    {
        $ids = [];

        foreach ($studios as $studio) {
            if (!($name = $studio['name'] ?? null) || !($shikimoriId = $studio['id'] ?? null))
                continue;

            // Тоже только по ID — по той же причине, что и персоны.
            $company = Company::where('shikimori_id', $shikimoriId)->first();

            if (!$company) {
                $company = Company::create([
                    'name'         => $name,
                    'shikimori_id' => $shikimoriId,
                    'author_id'    => $author->id
                ]);

                $this->note("Заведена новая студия «{$name}».");
            }

            $ids[] = $company->id;
        }

        return $ids;
    }

    /**
     * Разбирает /roles на персонал и — при необходимости — добирает сэйю
     * поштучно через /characters/:id.
     *
     * @return array<array{shikimori_id:int,name:string,russian:?string,image:?string,role:PersonRole,details:?string}>
     */
    protected function collectPeople(array $roles, bool $withSeyu): array
    {
        $people = [];

        foreach ($roles as $entry) {
            if (!($person = $entry['person'] ?? null))
                continue;

            foreach ($entry['roles'] ?? [] as $index => $role) {
                if (!($mapped = ShikimoriMap::STAFF_ROLES[$role] ?? null))
                    continue;

                $people[] = [
                    'shikimori_id' => $person['id'],
                    'name'         => $person['name'],
                    'russian'      => $person['russian'] ?? null,
                    'image'        => $person['image']['original'] ?? null,
                    'role'         => $mapped,
                    // Точное название роли с Шикимори — чтобы «Автор оригинала»
                    // не потерялся, схлопнувшись в screenwriter.
                    'details'      => $entry['roles_russian'][$index] ?? $role
                ];
            }
        }

        // Считаем именно персонал: в $roles лежат ещё и персонажи, они к
        // пропущенным ролям отношения не имеют.
        $staffRows = array_sum(array_map(
            fn($entry) => empty($entry['person']) ? 0 : count($entry['roles'] ?? []),
            $roles
        ));

        $skipped = $staffRows - count($people);

        if ($skipped > 0)
            $this->note("Пропущено {$skipped} строк персонала — роли без соответствия в справочнике (аниматоры, раскадровка, планирование).");

        if ($withSeyu)
            $people = array_merge($people, $this->collectSeyu($roles));

        return $people;
    }

    protected function collectSeyu(array $roles): array
    {
        $characters = array_values(array_filter($roles, fn($entry) => !empty($entry['character'])));

        // Главные роли вперёд — если упрёмся в лимит, отрежется второстепенное.
        usort($characters, fn($a, $b) => (int)!in_array('Main', $a['roles'] ?? [])
            <=> (int)!in_array('Main', $b['roles'] ?? []));

        if (count($characters) > self::MAX_CHARACTERS) {
            $cut        = count($characters) - self::MAX_CHARACTERS;
            $characters = array_slice($characters, 0, self::MAX_CHARACTERS);

            $this->note("Персонажей больше лимита — озвучка взята только для первых " . self::MAX_CHARACTERS . ", пропущено {$cut}.");
        }

        $fetched = $this->client->characters(
            array_map(fn($entry) => $entry['character']['id'], $characters)
        );

        $seyu = [];

        foreach ($characters as $entry) {
            $character = $fetched[$entry['character']['id']] ?? null;

            if (!$character || empty($character['seyu']))
                continue;

            // seyu приходят вперемешку по языкам, без пометки языка.
            // Японская озвучка у Шикимори идёт первой — берём её.
            $actor = $character['seyu'][0];

            $seyu[] = [
                'shikimori_id' => $actor['id'],
                'name'         => $actor['name'],
                'russian'      => $actor['russian'] ?? null,
                'image'        => $actor['image']['original'] ?? null,
                'role'         => PersonRole::VoiceActor,
                'details'      => $entry['character']['russian'] ?: $entry['character']['name']
            ];
        }

        return $seyu;
    }

    /**
     * Выкачивает фотографии тех персон, которых в справочнике ещё нет.
     *
     * Делается ДО транзакции и пачками: раньше каждое фото качалось поштучно
     * внутри resolvePerson, то есть внутри транзакции — на большом тайтле это
     * держало блокировки всё время загрузки шести десятков файлов.
     *
     * @return array<string, string> shikimori_id персоны => путь на public-диске
     */
    protected function fetchPersonPhotos(array $people): array
    {
        $paths = [];

        foreach ($people as $item) {
            $key = (string)$item['shikimori_id'];

            if (isset($paths[$key]))
                continue;

            $image = $item['image'] ?? null;

            // Заглушки «нет фото» качать смысла нет.
            if (!$image || str_contains($image, '/assets/globals/missing'))
                continue;

            // Тем, кто уже есть в справочнике, фото не трогаем.
            if ($this->findPerson($item['shikimori_id']))
                continue;

            $paths[$key] = $image;
        }

        if (!$paths)
            return [];

        $stored = [];

        foreach ($this->client->downloadMany($paths) as $key => $body) {
            $stored[$key] = $this->storeImage($body, $paths[$key], 'people');
        }

        return $stored;
    }

    protected function attachPeople(Film $film, array $people, array $photos, User $author): void
    {
        $order   = 0;
        $created = 0;

        // Сортируем в том же порядке, в каком карточка их потом показывает.
        usort($people, fn($a, $b) => array_search($a['role']->value, ShikimoriMap::ROLE_ORDER)
            <=> array_search($b['role']->value, ShikimoriMap::ROLE_ORDER));

        $seen = [];

        foreach ($people as $item) {
            // Один и тот же человек может быть и режиссёром, и сценаристом —
            // это две строки. А вот дубль «тот же человек в той же роли» гасим.
            $key = $item['shikimori_id'] . ':' . $item['role']->value . ':' . $item['details'];

            if (isset($seen[$key]))
                continue;

            $seen[$key] = true;

            $person = $this->resolvePerson($item, $photos, $author, $created);

            $film->people()->create([
                'person_id'    => $person->id,
                'role'         => $item['role'],
                'role_details' => Str::limit($item['details'] ?? '', 250, ''),
                'order_id'     => ++$order
            ]);
        }

        if ($order > 0)
            $this->note("Привязано {$order} персон, из них новых в справочнике — {$created}.");
    }

    /**
     * Ищем человека по латинскому имени: в people оно лежит в original_name,
     * и это единственное, что можно надёжно сопоставить с Шикимори.
     */
    protected function resolvePerson(array $item, array $photos, User $author, int &$created): Person
    {
        if ($person = $this->findPerson($item['shikimori_id']))
            return $person;

        $person = Person::create([
            'name'          => $item['russian'] ?: $item['name'],
            'original_name' => $item['name'],
            'shikimori_id'  => $item['shikimori_id'],
            'photo'         => $photos[(string)$item['shikimori_id']] ?? null,
            'author_id'     => $author->id
        ]);

        $created++;

        return $person;
    }

    /**
     * Ищем только по shikimori_id.
     *
     * По имени не ищем сознательно: у Шикимори полно полных тёзок среди сэйю
     * и аниматоров, и поиск по original_name склеивал бы разных людей в одного.
     * Обратная сторона — с записями, заведёнными руками, совпадения не будет
     * никогда: у них shikimori_id пустой, и человек заведётся заново.
     */
    protected function findPerson(int $shikimoriId): ?Person
    {
        return Person::where('shikimori_id', $shikimoriId)->first();
    }

    /**
     * Фоном берём первый скриншот — своей «широкой» обложки у Шикимори нет.
     */
    protected function fetchBackground(int $shikimoriId): ?string
    {
        $screenshots = $this->client->screenshots($shikimoriId);

        return $this->fetchImage($screenshots[0]['original'] ?? null, 'films-bg');
    }

    /**
     * Кладём файл на тот же public-диск и в те же каталоги, что и ручная загрузка
     * в FilmController::update — иначе картинка не отдастся через /storage.
     */
    protected function fetchImage(?string $path, string $directory): ?string
    {
        if (!$path)
            return null;

        // Заглушки «нет фото» качать смысла нет.
        if (str_contains($path, '/assets/globals/missing'))
            return null;

        $body = $this->client->download($path);

        return $body ? $this->storeImage($body, $path, $directory) : null;
    }

    protected function storeImage(string $body, string $path, string $directory): string
    {
        $extension = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $name      = $directory . '/' . Str::random(40) . '.' . $extension;

        Storage::disk('public')->put($name, $body);

        return $name;
    }

    protected function note(string $message): void
    {
        $this->log[] = $message;
    }
}
