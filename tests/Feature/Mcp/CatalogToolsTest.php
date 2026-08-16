<?php

namespace Tests\Feature\Mcp;

use App\Enums\FilmFormat;
use App\Mcp\Servers\FilmManagerServer;
use App\Mcp\Tools\Fetch;
use App\Mcp\Tools\GetFilm;
use App\Mcp\Tools\GetPerson;
use App\Mcp\Tools\ListFacets;
use App\Mcp\Tools\Search;
use App\Mcp\Tools\SearchFilms;
use App\Mcp\Tools\SearchPeople;
use App\Models\Country;
use App\Models\Film;
use App\Models\FilmPerson;
use App\Models\Genre;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogToolsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    // --- search_films ---

    public function test_search_films_filters_by_name(): void
    {
        $user = $this->user();
        Film::factory()->create(['name' => 'Семь самураев', 'produced_year' => 1954]);
        Film::factory()->create(['name' => 'Расёмон', 'produced_year' => 1950]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SearchFilms::class, ['query' => 'самураев'])
            ->assertOk()
            ->assertSee('Семь самураев')
            ->assertDontSee('Расёмон');
    }

    public function test_search_films_filters_by_genre_name_not_id(): void
    {
        $user = $this->user();
        $drama = Genre::factory()->create(['name' => 'Драма']);

        $withGenre = Film::factory()->create(['name' => 'С жанром']);
        $withGenre->genres()->attach($drama);

        Film::factory()->create(['name' => 'Без жанра']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SearchFilms::class, ['genres' => ['драма']])
            ->assertOk()
            ->assertSee('С жанром')
            ->assertDontSee('Без жанра');
    }

    public function test_search_films_warns_about_unknown_genre(): void
    {
        $user = $this->user();
        Film::factory()->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SearchFilms::class, ['genres' => ['такого жанра нет']])
            ->assertOk()
            ->assertSee('warnings')
            ->assertSee('"total":0');
    }

    public function test_search_films_combines_filters_with_and(): void
    {
        $user = $this->user();

        Film::factory()->create([
            'name' => 'Подходит',
            'format' => FilmFormat::Series,
            'produced_year' => 2020,
        ]);

        // Тот же формат, но год вне диапазона: попасть в выдачу не должен.
        Film::factory()->create([
            'name' => 'Слишком старый',
            'format' => FilmFormat::Series,
            'produced_year' => 1999,
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SearchFilms::class, ['format' => 'series', 'year_from' => 2010])
            ->assertOk()
            ->assertSee('Подходит')
            ->assertDontSee('Слишком старый');
    }

    public function test_search_films_finds_by_person_name(): void
    {
        $user = $this->user();
        $person = Person::factory()->create(['name' => 'Акира Куросава']);
        $film = Film::factory()->create(['name' => 'Его фильм']);
        FilmPerson::factory()->create([
            'film_id' => $film->id,
            'person_id' => $person->id,
            'role' => 'director',
        ]);

        Film::factory()->create(['name' => 'Чужой фильм']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SearchFilms::class, ['person' => 'Куросава'])
            ->assertOk()
            ->assertSee('Его фильм')
            ->assertDontSee('Чужой фильм');
    }

    public function test_search_films_respects_pagination(): void
    {
        $user = $this->user();
        Film::factory()->count(5)->create();

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SearchFilms::class, ['per_page' => 2])
            ->assertOk()
            ->assertSee(['"total":5', '"pages":3', '"page":1']);
    }

    // --- get_film ---

    public function test_get_film_returns_absolute_urls(): void
    {
        $user = $this->user();
        $film = Film::factory()->create(['cover' => 'films/cover.webp']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetFilm::class, ['id' => $film->id])
            ->assertOk()
            ->assertSee(config('app.url').'/storage/films/cover.webp')
            ->assertSee(config('app.frontend_url').'/catalog/films/'.$film->id);
    }

    public function test_get_film_reports_missing_film_as_error(): void
    {
        FilmManagerServer::actingAs($this->user(), 'mcp')
            ->tool(GetFilm::class, ['id' => 999999])
            ->assertHasErrors();
    }

    public function test_get_film_rejects_missing_id(): void
    {
        FilmManagerServer::actingAs($this->user(), 'mcp')
            ->tool(GetFilm::class, [])
            ->assertHasErrors();
    }

    // --- people ---

    public function test_search_people_orders_by_number_of_films(): void
    {
        $user = $this->user();
        $rare = Person::factory()->create(['name' => 'Иванов Редкий']);
        $often = Person::factory()->create(['name' => 'Иванов Частый']);

        FilmPerson::factory()->create(['person_id' => $rare->id, 'role' => 'actor']);
        FilmPerson::factory()->count(3)->create(['person_id' => $often->id, 'role' => 'actor']);

        $response = FilmManagerServer::actingAs($user, 'mcp')
            ->tool(SearchPeople::class, ['query' => 'Иванов'])
            ->assertOk();

        $response->assertSee('Иванов Частый');

        $body = $this->content($response);
        $this->assertLessThan(
            strpos($body, 'Иванов Редкий'),
            strpos($body, 'Иванов Частый'),
            'Человек с большим числом работ должен идти первым.'
        );
    }

    public function test_get_person_returns_filmography(): void
    {
        $user = $this->user();
        $country = Country::factory()->create(['name' => 'Япония']);
        $person = Person::factory()->create(['name' => 'Хаяо Миядзаки', 'country_id' => $country->id]);
        $film = Film::factory()->create(['name' => 'Унесённые призраками']);

        FilmPerson::factory()->create([
            'film_id' => $film->id,
            'person_id' => $person->id,
            'role' => 'director',
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(GetPerson::class, ['id' => $person->id])
            ->assertOk()
            ->assertSee(['Унесённые призраками', 'director', 'Япония']);
    }

    // --- list_facets ---

    public function test_list_facets_returns_genre_names(): void
    {
        $user = $this->user();
        Genre::factory()->create(['name' => 'Вестерн']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(ListFacets::class, ['kind' => 'genres'])
            ->assertOk()
            ->assertSee('Вестерн');
    }

    public function test_list_facets_rejects_unknown_kind(): void
    {
        FilmManagerServer::actingAs($this->user(), 'mcp')
            ->tool(ListFacets::class, ['kind' => 'что-то ещё'])
            ->assertHasErrors();
    }

    // --- search / fetch (контракт коннекторов ChatGPT) ---

    public function test_search_returns_prefixed_ids(): void
    {
        $user = $this->user();
        $film = Film::factory()->create(['name' => 'Сталкер']);
        Person::factory()->create(['name' => 'Сталкер Персона']);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(Search::class, ['query' => 'Сталкер'])
            ->assertOk()
            ->assertSee(['"results"', 'film:'.$film->id, 'person:']);
    }

    public function test_fetch_returns_document_for_search_id(): void
    {
        $user = $this->user();
        $film = Film::factory()->create([
            'name' => 'Солярис',
            'description' => 'Океан думает.',
        ]);

        FilmManagerServer::actingAs($user, 'mcp')
            ->tool(Fetch::class, ['id' => 'film:'.$film->id])
            ->assertOk()
            ->assertSee(['"id":"film:'.$film->id.'"', 'Океан думает.', '"url"']);
    }

    public function test_fetch_rejects_malformed_id(): void
    {
        FilmManagerServer::actingAs($this->user(), 'mcp')
            ->tool(Fetch::class, ['id' => 'просто строка'])
            ->assertHasErrors();
    }

    /**
     * Текст ответа инструмента — нужен там, где важен порядок элементов.
     */
    private function content(object $response): string
    {
        $property = new \ReflectionProperty($response, 'response');

        return $property->getValue($response)->toArray()['result']['content'][0]['text'] ?? '';
    }
}
