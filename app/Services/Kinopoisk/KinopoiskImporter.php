<?php

namespace App\Services\Kinopoisk;

use App\Enums\FilmFormat;
use App\Enums\FilmModerationStatus;
use App\Enums\PersonRole;
use App\Enums\PersonSex;
use App\Models\Company;
use App\Models\Country;
use App\Models\Film;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Собирает карточку из данных, которые расширение браузера сняло со страниц
 * Кинопоиска (у Кинопоиска нет открытого API, а сервер к нему не ходит:
 * страницы закрыты капчей, читать их может только браузер пользователя).
 *
 * Сервер сам скачивает только картинки — и только с CDN Кинопоиска.
 */
class KinopoiskImporter
{
    /**
     * Жанры Кинопоиска, которые в каталоге живут тегами, а не жанрами.
     */
    protected const array TAG_ALIASES = [
        'аниме'           => 'аниме',
        'документальный'  => 'документальный',
        'короткометражка' => 'короткометражка',
        'детский'         => 'детский',
        'фильм-нуар'      => 'нуар',
    ];

    /**
     * «Жанры», которые на самом деле формат — он уже учтён в format.
     */
    protected const array FORMAT_GENRES = ['мультфильм'];

    /**
     * Откуда разрешено скачивать картинки. URL приходит от клиента, и без
     * списка эндпоинт превратился бы в прокси для запросов в любую сеть.
     */
    protected const array IMAGE_HOSTS = [
        'yandex.net',
        'kinopoisk-ru.clstorage.net',
    ];

    protected array $log = [];

    /**
     * Что из присланного уже есть в каталоге: карточка и люди.
     *
     * @param array<array{kinopoisk_id:int,name:string,original_name:?string,birth_date:?string}> $people
     *
     * @return array{film: ?Film, similar: array<Film>, unknown_people: array<int>}
     */
    public function lookup(int $kinopoiskId, ?string $name, ?string $originalName, ?int $year, array $people): array
    {
        $film    = Film::where('kinopoisk_id', $kinopoiskId)->first();
        $similar = $film ? [] : $this->similarFilms($name, $originalName, $year);

        $unknown = [];

        foreach ($people as $item) {
            if (!$this->findPerson($item))
                $unknown[] = (int)$item['kinopoisk_id'];
        }

        return [
            'film'           => $film,
            'similar'        => $similar,
            'unknown_people' => array_values(array_unique($unknown))
        ];
    }

    /**
     * @return array{film: Film, created: bool, log: array<string>}
     */
    public function import(array $data, User $author): array
    {
        $this->log = [];
        $data      = $this->decodeEntities($data);

        if ($existing = Film::where('kinopoisk_id', $data['kinopoisk_id'])->first()) {
            return [
                'film'    => $existing,
                'created' => false,
                'log'     => ["Уже импортировано — карточка #{$existing->id} «{$existing->name}». Повторный импорт пропущен."]
            ];
        }

        // Картинки качаем ДО транзакции: сеть внутри неё держала бы блокировки.
        $cover  = $this->fetchImage($data['poster_url'] ?? null, 'films');
        $photos = $this->fetchPersonPhotos($data['people'] ?? []);

        $film = DB::transaction(function () use ($data, $author, $cover, $photos) {
            $film = Film::create([
                'name'              => $data['name'],
                'original_name'     => $data['original_name'] ?? null,
                'kinopoisk_id'      => $data['kinopoisk_id'],
                'format'            => $this->format($data),
                'description'       => $data['description'] ?? null,
                'release_date'      => $data['release_date'] ?? null,
                'produced_year'     => $data['produced_year'] ?? null,
                'cover'             => $cover,
                'author_id'         => $author->id,
                'moderation_status' => FilmModerationStatus::Draft
            ]);

            [$genreIds, $tagIds] = $this->genreAndTagIds($data['genres'] ?? []);

            $film->genres()->sync($genreIds);
            $film->tags()->sync($tagIds);
            $film->countries()->sync($this->countryIds($data['countries'] ?? []));
            $film->companies()->sync($this->companyIds($data['companies'] ?? [], $author));

            $this->attachPeople($film, $data['people'] ?? [], $photos, $author);

            return $film;
        });

        $this->note("Карточка #{$film->id} «{$film->name}» создана в статусе «черновик».");

        return ['film' => $film, 'created' => true, 'log' => $this->log];
    }

    /**
     * Формат присылает расширение, но анимацию сверяем по жанрам сами:
     * у Кинопоиска «мини–сериал» в заголовке перебивал признак мультфильма,
     * и анимационный мини-сериал приезжал как обычный. Мини-мультсериала
     * в справочнике форматов нет — такие идут в мультсериалы.
     */
    protected function format(array $data): FilmFormat
    {
        $format = FilmFormat::from($data['format']);
        $genres = array_map(fn($genre) => mb_strtolower(trim($genre)), $data['genres'] ?? []);

        if (!array_intersect($genres, ['мультфильм', 'аниме']))
            return $format;

        return match ($format) {
            FilmFormat::Film                                                => FilmFormat::Cartoon,
            FilmFormat::Series, FilmFormat::MiniSeries                      => FilmFormat::CartoonSeries,
            default                                                         => $format
        };
    }

    /**
     * Карточки, заведённые руками до расширения: kinopoisk_id у них пустой,
     * поэтому узнаём их по названию и году — чтобы не завести дубль.
     *
     * @return array<Film>
     */
    protected function similarFilms(?string $name, ?string $originalName, ?int $year): array
    {
        if (!$name && !$originalName)
            return [];

        return Film::query()
                   ->whereNull('kinopoisk_id')
                   ->where(fn($query) => $query
                       ->when($name, fn($q) => $q->orWhere('name', $name))
                       ->when($originalName, fn($q) => $q->orWhere('original_name', $originalName)))
                   ->when($year, fn($query) => $query->where(fn($q) => $q
                       ->whereNull('produced_year')
                       ->orWhereBetween('produced_year', [$year - 1, $year + 1])))
                   ->limit(5)
                   ->get()
                   ->all();
    }

    /**
     * @return array{0: array<int>, 1: array<int>}
     */
    protected function genreAndTagIds(array $names): array
    {
        $genres = [];
        $tags   = [];

        foreach ($names as $name) {
            $name = mb_strtolower(trim($name));

            if (!$name || in_array($name, self::FORMAT_GENRES))
                continue;

            if ($alias = self::TAG_ALIASES[$name] ?? null) {
                if ($tag = Tag::whereRaw('LOWER(name) = ?', [$alias])->first())
                    $tags[] = $tag->id;
                else
                    $this->note("Тега «{$alias}» в справочнике нет — пропущен.");

                continue;
            }

            if ($genre = Genre::whereRaw('LOWER(name) = ?', [$name])->first())
                $genres[] = $genre->id;
            else
                $this->note("Жанра «{$name}» в справочнике нет — пропущен.");
        }

        return [array_values(array_unique($genres)), array_values(array_unique($tags))];
    }

    protected function countryIds(array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            if (!($name = trim($name)))
                continue;

            $country = Country::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if (!$country) {
                $country = Country::create(['name' => $name]);
                $this->note("Заведена новая страна «{$name}».");
            }

            $ids[] = $country->id;
        }

        return array_values(array_unique($ids));
    }

    protected function companyIds(array $names, User $author): array
    {
        $ids = [];

        foreach ($names as $name) {
            if (!($name = trim($name)))
                continue;

            $company = Company::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if (!$company) {
                $company = Company::create(['name' => $name, 'author_id' => $author->id]);
                $this->note("Заведена новая студия «{$name}».");
            }

            $ids[] = $company->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Фото качаем только тем, кого в справочнике ещё нет.
     *
     * @return array<string, string> kinopoisk_id => путь на public-диске
     */
    protected function fetchPersonPhotos(array $people): array
    {
        $stored = [];

        foreach ($people as $item) {
            $key = (string)$item['kinopoisk_id'];

            if (isset($stored[$key]) || empty($item['photo_url']) || $this->findPerson($item))
                continue;

            if ($path = $this->fetchImage($item['photo_url'], 'people'))
                $stored[$key] = $path;
        }

        return $stored;
    }

    protected function attachPeople(Film $film, array $people, array $photos, User $author): void
    {
        $order   = 0;
        $created = 0;
        $seen    = [];

        // В мультфильмах актёры Кинопоиска — это озвучка; в каталоге они
        // так и записываются (voice-actor), обычных actor у анимации нет.
        $animated = in_array($film->format, [FilmFormat::Cartoon, FilmFormat::CartoonSeries]);

        foreach ($people as $item) {
            $role = PersonRole::from($item['role']);

            if ($animated && $role === PersonRole::Actor)
                $role = PersonRole::VoiceActor;
            $key  = $item['kinopoisk_id'] . ':' . $role->value;

            // Один человек в одной роли — одна строка (на Кинопоиске актёр
            // с двумя персонажами иногда встречается в списке дважды).
            if (isset($seen[$key]))
                continue;

            $seen[$key] = true;

            $person = $this->resolvePerson($item, $photos, $author, $created);

            $film->people()->create([
                'person_id'    => $person->id,
                'role'         => $role,
                'role_details' => self::shortenRoles($item['role_details'] ?? null),
                'order_id'     => ++$order
            ]);
        }

        if ($order > 0)
            $this->note("Привязано {$order} персон, из них новых в справочнике — {$created}.");
    }

    protected function resolvePerson(array $item, array $photos, User $author, int &$created): Person
    {
        if ($person = $this->findPerson($item)) {
            // Найденному по имени запоминаем ID — дальше он узнаётся без догадок.
            if (!$person->kinopoisk_id)
                $person->update(['kinopoisk_id' => $item['kinopoisk_id']]);

            return $person;
        }

        $person = Person::create([
            'name'          => self::cleanName($item['name']),
            'original_name' => self::cleanName($item['original_name'] ?? null),
            'kinopoisk_id'  => $item['kinopoisk_id'],
            'birth_date'    => $item['birth_date'] ?? null,
            'death_date'    => $item['death_date'] ?? null,
            'sex'           => PersonSex::tryFrom($item['sex'] ?? ''),
            'photo'         => $photos[(string)$item['kinopoisk_id']] ?? null,
            'author_id'     => $author->id
        ]);

        $created++;

        return $person;
    }

    /**
     * Сначала по kinopoisk_id. Потом — по имени, но осторожно: справочник
     * заполнялся руками с того же Кинопоиска, и без поиска по имени каждый
     * импорт заводил бы дубли Хью Джекмана. Тёзок при этом склеивать нельзя,
     * поэтому совпадение засчитывается, только если кандидат один и у него
     * сходится оригинальное имя (или, если его нет, дата рождения).
     */
    protected function findPerson(array $item): ?Person
    {
        if ($person = Person::where('kinopoisk_id', $item['kinopoisk_id'])->first())
            return $person;

        $candidates = Person::whereNull('kinopoisk_id')
                            ->where('name', self::cleanName($item['name']))
                            ->limit(3)
                            ->get();

        if ($candidates->count() !== 1)
            return null;

        $candidate = $candidates->first();
        $original  = self::cleanName($item['original_name'] ?? null);
        $birth     = $item['birth_date'] ?? null;

        if ($candidate->original_name && $original)
            return mb_strtolower($candidate->original_name) === mb_strtolower($original) ? $candidate : null;

        if ($candidate->birth_date && $birth)
            return $candidate->birth_date->format('Y-m-d') === $birth ? $candidate : null;

        // Сверить нечем ни по одному признаку — имя совпало, кандидат единственный.
        return $candidate;
    }

    /**
     * У озвучки мультсериалов роли — десятки персонажей через « / ».
     * В колонку (255) влезает не всё; режем по целому персонажу, а не посреди имени.
     */
    protected static function shortenRoles(?string $roles, int $limit = 250): ?string
    {
        $roles = trim((string)$roles);

        if ($roles === '' || mb_strlen($roles) <= $limit)
            return $roles ?: null;

        $cut = mb_substr($roles, 0, $limit - 2);
        $end = mb_strrpos($cut, ' / ');

        return ($end ? mb_substr($cut, 0, $end) : $cut) . ' …';
    }

    /**
     * В JSON-LD Кинопоиска часть символов закодирована HTML-сущностями
     * («X-Men &apos;97»), а в БД нужен обычный текст. Ссылки не трогаем.
     */
    protected function decodeEntities(array $data): array
    {
        array_walk_recursive($data, function (&$value, $key) {
            if (is_string($value) && !str_ends_with((string)$key, '_url'))
                $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        });

        return $data;
    }

    /**
     * На странице состава к имени дописывается, как человек указан в титрах:
     * «Rebecca Romijn (в титрах: Rebecca Romijn-Stamos)». В справочник — без этого.
     */
    protected static function cleanName(?string $name): ?string
    {
        if ($name === null)
            return null;

        return trim(preg_replace('/\s*\(в титрах:[^)]*\)/u', '', $name)) ?: null;
    }

    protected function fetchImage(?string $url, string $directory): ?string
    {
        if (!$url || !$this->isAllowedImageUrl($url))
            return null;

        try {
            $response = Http::timeout(20)->retry(2, 500, throw: false)->get($url);
        } catch (ConnectionException) {
            $this->note("Не удалось скачать картинку: {$url}");
            return null;
        }

        $type = (string)$response->header('Content-Type');

        if (!$response->successful() || !str_starts_with($type, 'image/')) {
            $this->note("Не удалось скачать картинку: {$url}");
            return null;
        }

        $extension = match (true) {
            str_contains($type, 'webp') => 'webp',
            str_contains($type, 'png')  => 'png',
            str_contains($type, 'avif') => 'avif',
            default                     => 'jpg'
        };

        $name = $directory . '/' . Str::random(40) . '.' . $extension;

        Storage::disk('public')->put($name, $response->body());

        return $name;
    }

    protected function isAllowedImageUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || !($host = $parts['host'] ?? null))
            return false;

        foreach (self::IMAGE_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed))
                return true;
        }

        return false;
    }

    protected function note(string $message): void
    {
        $this->log[] = $message;
    }
}
