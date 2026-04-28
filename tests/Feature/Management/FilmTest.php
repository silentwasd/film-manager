<?php

namespace Tests\Feature\Management;

use App\Enums\FilmFormat;
use App\Enums\UserRole;
use App\Models\Country;
use App\Models\Film;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/films')->assertUnauthorized();
    }

    public function test_index_returns_all_films(): void
    {
        $user = User::factory()->create();
        Film::factory()->count(3)->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->getJson('/api/management/films')
             ->assertOk()
             ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_name(): void
    {
        $user = User::factory()->create();
        Film::factory()->create(['author_id' => $user->id, 'name' => 'The Matrix']);
        Film::factory()->create(['author_id' => $user->id, 'name' => 'Inception']);

        $response = $this->actingAs($user)->getJson('/api/management/films?name=Matrix');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('The Matrix', $response->json('data.0.name'));
    }

    public function test_index_filters_by_format(): void
    {
        $user = User::factory()->create();
        Film::factory()->create(['author_id' => $user->id, 'format' => FilmFormat::Film]);
        Film::factory()->create(['author_id' => $user->id, 'format' => FilmFormat::Series]);

        $response = $this->actingAs($user)->getJson('/api/management/films?format=series');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);
        $film->genres()->attach($genre);
        Film::factory()->create(['author_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/management/films?genres[]={$genre->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    // --- store ---

    public function test_store_creates_film(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/management/films', [
            'name'   => 'New Film',
            'format' => 'film',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'New Film');
        $this->assertDatabaseHas('films', ['name' => 'New Film', 'author_id' => $user->id]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/management/films', ['name' => 'Film', 'format' => 'film'])
             ->assertUnauthorized();
    }

    public function test_store_fails_without_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/management/films', [])->assertUnprocessable();
    }

    // --- show ---

    public function test_show_returns_film(): void
    {
        $film = Film::factory()->create(['name' => 'Test Film']);

        $this->getJson("/api/management/films/{$film->id}")
             ->assertOk()
             ->assertJsonPath('data.name', 'Test Film');
    }

    public function test_show_is_public(): void
    {
        $film = Film::factory()->create();

        $this->getJson("/api/management/films/{$film->id}")->assertOk();
    }

    // --- update ---

    public function test_update_by_author(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->putJson("/api/management/films/{$film->id}", [
                 'name'   => 'Updated Name',
                 'format' => 'film',
             ])
             ->assertOk();

        $this->assertDatabaseHas('films', ['id' => $film->id, 'name' => 'Updated Name']);
    }

    public function test_update_by_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $film = Film::factory()->create();

        $this->actingAs($admin)
             ->putJson("/api/management/films/{$film->id}", [
                 'name'   => 'Admin Updated',
                 'format' => 'film',
             ])
             ->assertOk();
    }

    public function test_update_forbidden_for_non_author(): void
    {
        $author = User::factory()->create();
        $other  = User::factory()->create();
        $film   = Film::factory()->create(['author_id' => $author->id]);

        $this->actingAs($other)
             ->putJson("/api/management/films/{$film->id}", [
                 'name'   => 'Hacked',
                 'format' => 'film',
             ])
             ->assertForbidden();
    }

    public function test_update_syncs_relations(): void
    {
        $user  = User::factory()->create();
        $film  = Film::factory()->create(['author_id' => $user->id]);
        $genre = Genre::factory()->create();

        $this->actingAs($user)
             ->putJson("/api/management/films/{$film->id}", [
                 'name'   => $film->name,
                 'format' => $film->format->value,
                 'genres' => [$genre->id],
             ])
             ->assertOk();

        $this->assertDatabaseHas('film_genre', ['film_id' => $film->id, 'genre_id' => $genre->id]);
    }

    // --- destroy ---

    public function test_destroy_by_author(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->deleteJson("/api/management/films/{$film->id}")
             ->assertOk();

        $this->assertDatabaseMissing('films', ['id' => $film->id]);
    }

    public function test_destroy_by_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $film  = Film::factory()->create();

        $this->actingAs($admin)
             ->deleteJson("/api/management/films/{$film->id}")
             ->assertOk();
    }

    public function test_destroy_forbidden_for_non_author(): void
    {
        $author = User::factory()->create();
        $other  = User::factory()->create();
        $film   = Film::factory()->create(['author_id' => $author->id]);

        $this->actingAs($other)
             ->deleteJson("/api/management/films/{$film->id}")
             ->assertForbidden();
    }

    public function test_destroy_forbidden_when_other_users_watching(): void
    {
        $author  = User::factory()->create();
        $watcher = User::factory()->create();
        $film    = Film::factory()->create(['author_id' => $author->id]);
        $film->watchers()->create(['watcher_id' => $watcher->id, 'status' => 'to-watch']);

        $this->actingAs($author)
             ->deleteJson("/api/management/films/{$film->id}")
             ->assertForbidden();
    }
}
