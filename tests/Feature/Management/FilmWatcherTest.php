<?php

namespace Tests\Feature\Management;

use App\Models\Film;
use App\Models\FilmWatcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmWatcherTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/film-watchers')->assertUnauthorized();
    }

    public function test_index_returns_only_user_watchlist(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $film1 = Film::factory()->create();
        $film2 = Film::factory()->create();

        FilmWatcher::factory()->create(['film_id' => $film1->id, 'watcher_id' => $user->id]);
        FilmWatcher::factory()->create(['film_id' => $film2->id, 'watcher_id' => $other->id]);

        $this->actingAs($user)
             ->getJson('/api/management/film-watchers')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    // --- store ---

    public function test_store_adds_film_to_watchlist(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/film-watchers', [
                 'film_id' => $film->id,
                 'status'  => 'to-watch',
             ])
             ->assertOk();

        $this->assertDatabaseHas('film_watchers', ['film_id' => $film->id, 'watcher_id' => $user->id]);
    }

    public function test_store_requires_authentication(): void
    {
        $film = Film::factory()->create();

        $this->postJson('/api/management/film-watchers', [
            'film_id' => $film->id,
            'status'  => 'to-watch',
        ])->assertUnauthorized();
    }

    public function test_store_fails_with_invalid_status(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/film-watchers', [
                 'film_id' => $film->id,
                 'status'  => 'invalid',
             ])
             ->assertUnprocessable();
    }

    // --- byFilm ---

    public function test_by_film_returns_watcher_entry(): void
    {
        $user    = User::factory()->create();
        $film    = Film::factory()->create();
        $watcher = FilmWatcher::factory()->create(['film_id' => $film->id, 'watcher_id' => $user->id]);

        $this->actingAs($user)
             ->getJson("/api/management/film-watchers/by-film/{$film->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $watcher->id);
    }

    public function test_by_film_returns_404_when_not_watching(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->actingAs($user)
             ->getJson("/api/management/film-watchers/by-film/{$film->id}")
             ->assertNotFound();
    }

    // --- update ---

    public function test_update_changes_watch_status(): void
    {
        $user    = User::factory()->create();
        $film    = Film::factory()->create();
        $watcher = FilmWatcher::factory()->create(['film_id' => $film->id, 'watcher_id' => $user->id, 'status' => 'to-watch']);

        $this->actingAs($user)
             ->putJson("/api/management/film-watchers/{$watcher->id}", ['status' => 'watched'])
             ->assertOk();

        $this->assertDatabaseHas('film_watchers', ['id' => $watcher->id, 'status' => 'watched']);
    }

    public function test_update_forbidden_for_other_user(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $film    = Film::factory()->create();
        $watcher = FilmWatcher::factory()->create(['film_id' => $film->id, 'watcher_id' => $owner->id]);

        $this->actingAs($other)
             ->putJson("/api/management/film-watchers/{$watcher->id}", ['status' => 'watched'])
             ->assertForbidden();
    }

    // --- destroy ---

    public function test_destroy_removes_from_watchlist(): void
    {
        $user    = User::factory()->create();
        $film    = Film::factory()->create();
        $watcher = FilmWatcher::factory()->create(['film_id' => $film->id, 'watcher_id' => $user->id]);

        $this->actingAs($user)
             ->deleteJson("/api/management/film-watchers/{$watcher->id}")
             ->assertOk();

        $this->assertDatabaseMissing('film_watchers', ['id' => $watcher->id]);
    }

    public function test_destroy_forbidden_for_other_user(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $film    = Film::factory()->create();
        $watcher = FilmWatcher::factory()->create(['film_id' => $film->id, 'watcher_id' => $owner->id]);

        $this->actingAs($other)
             ->deleteJson("/api/management/film-watchers/{$watcher->id}")
             ->assertForbidden();
    }
}
