<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Film;
use App\Models\FilmWatcher;
use App\Models\Franchise;
use App\Models\Genre;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Главная страница одним запросом: витрина, подборки, отзывы, франшизы, жанры.
 *
 * Общая часть кэшируется на 10 минут; витрина меняется раз в сутки (сид — дата).
 * Полки «Досмотреть» и «В планах» — только для вошедшего, токен необязателен:
 * гость получает ту же страницу без них.
 */
class HomeController extends Controller
{
    protected const CACHE_TTL = 600;

    public function index(Request $request)
    {
        $shared = Cache::remember('home:' . now()->toDateString(), self::CACHE_TTL, fn() => [
            'stats'      => $this->stats(),
            'spotlight'  => $this->spotlight(),
            'premieres'  => $this->premieres(),
            'top'        => $this->top(),
            'reviews'    => $this->reviews(),
            'franchises' => $this->franchises(),
            'genres'     => $this->genres(),
            'recent'     => $this->recent(),
        ]);

        /** @var User|null $user */
        $user = $request->user('sanctum');

        return ['data' => $shared + ['personal' => $user ? $this->personal($user) : null]];
    }

    /**
     * Случайный тайтл с постером. Вошедшему — из ещё не просмотренных им.
     */
    public function random(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        $film = Film::query()
            ->whereNotNull('cover')
            ->whereNotNull('description')
            ->where(fn($q) => $q->whereNull('release_date')->orWhere('release_date', '<=', now()))
            ->when($user, fn($q) => $q->whereDoesntHave('watchers', fn($w) => $w
                ->where('watcher_id', $user->id)
                ->whereIn('status', ['watched', 'dropped'])
            ))
            ->inRandomOrder()
            ->first(['id']);

        return ['data' => ['id' => $film?->id]];
    }

    protected function stats(): array
    {
        $formats = Film::query()
            ->selectRaw("SUM(format IN ('film', 'cartoon')) AS films, SUM(format NOT IN ('film', 'cartoon')) AS series")
            ->first();

        return [
            'films'   => (int)$formats->films,
            'series'  => (int)$formats->series,
            'people'  => Person::count(),
            'reviews' => Feedback::whereNotNull('text')->count(),
        ];
    }

    /**
     * Витрина — тайтлы с фоновой обложкой и описанием. RAND(сид) с датой
     * даёт одну и ту же подборку весь день и новую на следующий.
     */
    protected function spotlight(): array
    {
        $seed = (int)now()->format('Ymd');

        return Film::query()
            ->whereNotNull('background_cover')
            ->whereNotNull('cover')
            ->whereNotNull('description')
            ->with('genres:id,name,slug')
            ->orderByRaw('RAND(?)', [$seed])
            ->limit(5)
            ->get()
            ->map(fn(Film $film) => $this->film($film) + [
                    'original_name'    => $film->original_name,
                    'description'      => $film->description,
                    'background_cover' => $film->background_cover,
                    'genres'           => $film->genres->map(fn(Genre $g) => ['name' => $g->name, 'slug' => $g->slug])->take(3)->values(),
                ])
            ->all();
    }

    /**
     * Ближайшие премьеры (по возрастанию даты), за ними — вышедшее за последний год
     * (от свежего к старому).
     */
    protected function premieres(): array
    {
        $upcoming = Film::query()
            ->whereNotNull('cover')
            ->where('release_date', '>', now())
            ->orderBy('release_date')
            ->limit(8)
            ->get();

        $released = Film::query()
            ->whereNotNull('cover')
            ->whereBetween('release_date', [now()->subYear(), now()])
            ->orderByDesc('release_date')
            ->limit(20 - $upcoming->count())
            ->get();

        return $upcoming->concat($released)->map(fn(Film $film) => $this->film($film))->all();
    }

    /**
     * Любимое зрителями: лайки минус дизлайки, при равенстве — у кого больше отметок
     * «просмотрено». Девять, а не десять: двузначная цифра за постером не помещается.
     */
    protected function top(): array
    {
        // Только отзывы на тайтл целиком: оценки отдельных сезонов в топ
        // не идут, иначе сериал с пятью сезонами набирал бы впятеро больше.
        $scores = Feedback::query()
            ->whereNull('season')
            ->select('film_id')
            ->selectRaw('SUM(reaction) AS score, SUM(reaction = 1) AS likes')
            ->groupBy('film_id')
            ->having('score', '>', 0);

        $films = Film::query()
            ->joinSub($scores, 's', 's.film_id', '=', 'films.id')
            ->whereNotNull('cover')
            ->select('films.*', 's.score', 's.likes')
            ->withCount(['watchers as watched_count' => fn($q) => $q->where('status', 'watched')])
            ->orderByDesc('s.score')
            ->orderByDesc('watched_count')
            ->orderByDesc('films.id')
            ->limit(9)
            ->get();

        return $films->map(fn(Film $film) => $this->film($film) + [
                'likes'   => (int)$film->likes,
                'watched' => (int)$film->watched_count,
            ])->all();
    }

    protected function reviews(): array
    {
        return Feedback::query()
            ->whereNotNull('text')
            ->where('text', '!=', '')
            ->whereHas('film')
            ->with(['user:id,name', 'film'])
            ->latest('updated_at')
            ->limit(9)
            ->get()
            ->map(fn(Feedback $item) => [
                'id'         => $item->id,
                'text'       => $item->text,
                'reaction'   => $item->reaction,
                'season'     => $item->season,
                'user'       => $item->user?->name,
                'updated_at' => $item->updated_at,
                'film'       => $this->film($item->film),
            ])
            ->all();
    }

    /**
     * Франшизы с коллажом из четырёх свежих постеров и разбросом лет.
     */
    protected function franchises(): array
    {
        $franchises = Franchise::query()
            ->withCount('films')
            ->having('films_count', '>', 1)
            ->orderByDesc('films_count')
            ->limit(16)
            ->get();

        return $franchises->map(function (Franchise $franchise) {
            $films = $franchise->films()
                ->whereNotNull('produced_year')
                ->orderByDesc('produced_year')
                ->get(['films.id', 'films.cover', 'films.cover_thumb', 'films.produced_year']);

            return [
                'id'     => $franchise->id,
                'name'   => $franchise->name,
                'count'  => $franchise->films_count,
                'from'   => $films->min('produced_year'),
                'to'     => $films->max('produced_year'),
                'covers' => $films->whereNotNull('cover')
                    ->take(4)
                    ->map(fn(Film $f) => $f->cover_thumb ?: $f->cover)
                    ->values(),
            ];
        })->all();
    }

    protected function genres(): array
    {
        return Genre::query()
            ->whereNotNull('slug')
            ->withCount('films')
            ->having('films_count', '>', 0)
            ->orderByDesc('films_count')
            ->limit(16)
            ->get()
            ->map(fn(Genre $genre) => [
                'name'  => $genre->name,
                'slug'  => $genre->slug,
                'icon'  => $genre->icon,
                'count' => $genre->films_count,
            ])
            ->all();
    }

    protected function recent(): array
    {
        return Film::query()
            ->whereNotNull('cover')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn(Film $film) => $this->film($film))
            ->all();
    }

    /**
     * Полки вошедшего: что досмотреть и что в планах — от последних отметок.
     */
    protected function personal(User $user): array
    {
        $shelf = fn(string $status) => FilmWatcher::query()
            ->where('watcher_id', $user->id)
            ->where('status', $status)
            ->whereHas('film')
            ->with('film')
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn(FilmWatcher $w) => $this->film($w->film) + ['status' => $status])
            ->all();

        return [
            'name'        => $user->name,
            'must_finish' => $shelf('must-finish'),
            'to_watch'    => $shelf('to-watch'),
            'watched'     => FilmWatcher::where('watcher_id', $user->id)->where('status', 'watched')->count(),
        ];
    }

    /**
     * Минимум для карточки на постере.
     */
    protected function film(Film $film): array
    {
        return [
            'id'            => $film->id,
            'name'          => $film->name,
            'format'        => $film->format,
            'cover'         => $film->cover,
            'cover_thumb'   => $film->cover_thumb,
            'produced_year' => $film->produced_year,
            'release_date'  => $film->release_date?->format('Y-m-d'),
        ];
    }
}
