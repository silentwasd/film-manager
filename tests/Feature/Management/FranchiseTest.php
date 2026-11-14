<?php

namespace Tests\Feature\Management;

use App\Enums\FilmWatchStatus;
use App\Enums\UserRole;
use App\Models\Film;
use App\Models\FilmWatcher;
use App\Models\Franchise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseTest extends TestCase
{
    use RefreshDatabase;

    // --- index / store ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/franchises')->assertUnauthorized();
    }

    public function test_index_returns_franchises_with_counts(): void
    {
        $user      = User::factory()->create();
        $franchise = Franchise::factory()->create(['author_id' => $user->id]);
        $franchise->films()->attach(Film::factory()->count(3)->create());

        $this->actingAs($user)
             ->getJson('/api/management/franchises')
             ->assertOk()
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.films_count', 3);
    }

    public function test_store_creates_franchise(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/franchises', ['name' => 'Черепашки-ниндзя'])
             ->assertOk();

        $this->assertDatabaseHas('franchises', ['name' => 'Черепашки-ниндзя', 'author_id' => $user->id]);
    }

    // --- show ---

    public function test_show_is_public_and_lists_films_newest_first(): void
    {
        $franchise = Franchise::factory()->create();
        $old       = Film::factory()->create(['produced_year' => 1990]);
        $new       = Film::factory()->create(['produced_year' => 2023]);
        $franchise->films()->attach([$old->id, $new->id]);

        $this->getJson("/api/management/franchises/{$franchise->id}")
             ->assertOk()
             ->assertJsonPath('data.name', $franchise->name)
             ->assertJsonPath('data.films.0.id', $new->id)
             ->assertJsonPath('data.films.1.id', $old->id);
    }

    // --- watch-statuses ---

    public function test_watch_statuses_only_for_franchise_films(): void
    {
        $user      = User::factory()->create();
        $franchise = Franchise::factory()->create();
        $inside    = Film::factory()->create();
        $outside   = Film::factory()->create();
        $franchise->films()->attach($inside);

        FilmWatcher::factory()->create(['watcher_id' => $user->id, 'film_id' => $inside->id, 'status' => FilmWatchStatus::Watched]);
        FilmWatcher::factory()->create(['watcher_id' => $user->id, 'film_id' => $outside->id, 'status' => FilmWatchStatus::Watched]);

        $this->actingAs($user)
             ->getJson("/api/management/franchises/{$franchise->id}/watch-statuses")
             ->assertOk()
             ->assertExactJson(['data' => [(string)$inside->id => FilmWatchStatus::Watched->value]]);
    }

    public function test_watch_statuses_require_authentication(): void
    {
        $franchise = Franchise::factory()->create();

        $this->getJson("/api/management/franchises/{$franchise->id}/watch-statuses")->assertUnauthorized();
    }

    // --- update / destroy ---

    public function test_update_forbidden_for_non_author(): void
    {
        $franchise = Franchise::factory()->create();

        $this->actingAs(User::factory()->create())
             ->putJson("/api/management/franchises/{$franchise->id}", ['name' => 'Hacked'])
             ->assertForbidden();
    }

    public function test_update_by_admin(): void
    {
        $franchise = Franchise::factory()->create();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
             ->putJson("/api/management/franchises/{$franchise->id}", ['name' => 'Renamed'])
             ->assertOk();

        $this->assertDatabaseHas('franchises', ['id' => $franchise->id, 'name' => 'Renamed']);
    }

    public function test_destroy_forbidden_for_author_when_has_films(): void
    {
        $user      = User::factory()->create();
        $franchise = Franchise::factory()->create(['author_id' => $user->id]);
        $franchise->films()->attach(Film::factory()->create());

        $this->actingAs($user)
             ->deleteJson("/api/management/franchises/{$franchise->id}")
             ->assertForbidden();
    }

    public function test_destroy_by_author_when_empty(): void
    {
        $user      = User::factory()->create();
        $franchise = Franchise::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->deleteJson("/api/management/franchises/{$franchise->id}")
             ->assertOk();

        $this->assertDatabaseMissing('franchises', ['id' => $franchise->id]);
    }

    // --- карточка фильма ---

    public function test_film_update_syncs_franchises(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);
        $a    = Franchise::factory()->create();
        $b    = Franchise::factory()->create();
        $film->franchises()->attach($a);

        $this->actingAs($user)
             ->putJson("/api/management/films/{$film->id}", [
                 'name'       => $film->name,
                 'format'     => 'film',
                 'franchises' => [$b->id],
             ])
             ->assertOk();

        $this->assertEquals([$b->id], $film->franchises()->pluck('franchises.id')->all());
    }

    public function test_film_show_includes_franchises(): void
    {
        $film      = Film::factory()->create();
        $franchise = Franchise::factory()->create();
        $film->franchises()->attach($franchise);

        $this->getJson("/api/management/films/{$film->id}")
             ->assertOk()
             ->assertJsonPath('data.franchises.0.name', $franchise->name);
    }

    public function test_film_show_includes_franchise_siblings(): void
    {
        $film      = Film::factory()->create();
        $sibling   = Film::factory()->create();
        $franchise = Franchise::factory()->create();
        $franchise->films()->attach([$film->id, $sibling->id]);

        $this->getJson("/api/management/films/{$film->id}")
             ->assertOk()
             ->assertJsonCount(2, 'data.franchises.0.films');
    }

    // --- импорт с Кинопоиска ---

    private function kinopoiskPayload(array $extra = []): array
    {
        return [
            'kinopoisk_id' => 7001,
            'name'         => 'Черепашки-ниндзя',
            'format'       => 'film',
            ...$extra,
        ];
    }

    public function test_kinopoisk_import_creates_franchise_by_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/films/import/kinopoisk', $this->kinopoiskPayload(['franchises' => ['Черепашки-ниндзя']]))
             ->assertCreated()
             ->assertJsonPath('data.franchises.0.name', 'Черепашки-ниндзя');

        $this->assertDatabaseCount('franchises', 1);
    }

    public function test_kinopoisk_import_reuses_franchise_case_insensitively(): void
    {
        $user      = User::factory()->create();
        $franchise = Franchise::factory()->create(['name' => 'Черепашки-ниндзя']);

        $this->actingAs($user)
             ->postJson('/api/management/films/import/kinopoisk', $this->kinopoiskPayload(['franchises' => ['черепашки-ниндзя']]))
             ->assertCreated();

        $this->assertDatabaseCount('franchises', 1);
        $this->assertDatabaseHas('film_franchise', ['franchise_id' => $franchise->id]);
    }

    public function test_kinopoisk_reimport_adds_franchise_to_existing_film(): void
    {
        $user  = User::factory()->create();
        $film  = Film::factory()->create(['kinopoisk_id' => 7001]);
        $other = Franchise::factory()->create();
        $film->franchises()->attach($other);

        $this->actingAs($user)
             ->postJson('/api/management/films/import/kinopoisk', $this->kinopoiskPayload(['franchises' => ['Черепашки-ниндзя']]))
             ->assertOk()
             ->assertJsonPath('created', false);

        $this->assertEquals(2, $film->franchises()->count());
    }
}
