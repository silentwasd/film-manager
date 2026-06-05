<?php

namespace Tests\Feature\Management;

use App\Models\Collection;
use App\Models\CollectionFilm;
use App\Models\Film;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionFilmTest extends TestCase
{
    use RefreshDatabase;

    // --- store ---

    public function test_store_adds_film_to_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film = Film::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/management/collections/{$collection->id}/films", ['film_id' => $film->id])
            ->assertCreated();

        $this->assertDatabaseHas('collection_films', ['collection_id' => $collection->id, 'film_id' => $film->id]);
    }

    public function test_store_assigns_incrementing_position(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film1 = Film::factory()->create();
        $film2 = Film::factory()->create();

        $this->actingAs($user)->postJson("/api/management/collections/{$collection->id}/films", ['film_id' => $film1->id])->assertCreated();
        $this->actingAs($user)->postJson("/api/management/collections/{$collection->id}/films", ['film_id' => $film2->id])->assertCreated();

        $this->assertDatabaseHas('collection_films', ['film_id' => $film1->id, 'position' => 1]);
        $this->assertDatabaseHas('collection_films', ['film_id' => $film2->id, 'position' => 2]);
    }

    public function test_store_inserts_at_specific_position(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film1 = Film::factory()->create();
        $film2 = Film::factory()->create();
        $film3 = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film1->id, 'position' => 1]);
        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film2->id, 'position' => 2]);

        $this->actingAs($user)
            ->postJson("/api/management/collections/{$collection->id}/films", ['film_id' => $film3->id, 'position' => 1])
            ->assertCreated();

        $this->assertDatabaseHas('collection_films', ['film_id' => $film3->id, 'position' => 1]);
        $this->assertDatabaseHas('collection_films', ['film_id' => $film1->id, 'position' => 2]);
        $this->assertDatabaseHas('collection_films', ['film_id' => $film2->id, 'position' => 3]);
    }

    public function test_store_rejects_duplicate_film(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film->id]);

        $this->actingAs($user)
            ->postJson("/api/management/collections/{$collection->id}/films", ['film_id' => $film->id])
            ->assertUnprocessable();
    }

    public function test_store_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();
        $film = Film::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/management/collections/{$collection->id}/films", ['film_id' => $film->id])
            ->assertForbidden();
    }

    public function test_store_saves_note(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film = Film::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/management/collections/{$collection->id}/films", [
                'film_id' => $film->id,
                'note' => 'Мой любимый фильм',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('collection_films', ['film_id' => $film->id, 'note' => 'Мой любимый фильм']);
    }

    // --- update ---

    public function test_update_changes_note(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film = Film::factory()->create();
        $collectionFilm = CollectionFilm::factory()->create([
            'collection_id' => $collection->id,
            'film_id' => $film->id,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/management/collections/{$collection->id}/films/{$film->id}", ['note' => 'Обновлённая заметка'])
            ->assertOk();

        $this->assertDatabaseHas('collection_films', ['id' => $collectionFilm->id, 'note' => 'Обновлённая заметка']);
    }

    public function test_update_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();
        $film = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film->id]);

        $this->actingAs($user)
            ->patchJson("/api/management/collections/{$collection->id}/films/{$film->id}", ['note' => 'X'])
            ->assertForbidden();
    }

    // --- destroy ---

    public function test_destroy_removes_film_from_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film = Film::factory()->create();
        $collectionFilm = CollectionFilm::factory()->create([
            'collection_id' => $collection->id,
            'film_id' => $film->id,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/management/collections/{$collection->id}/films/{$film->id}")
            ->assertOk();

        $this->assertDatabaseMissing('collection_films', ['id' => $collectionFilm->id]);
    }

    public function test_destroy_renumbers_positions(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film1 = Film::factory()->create();
        $film2 = Film::factory()->create();
        $film3 = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film1->id, 'position' => 1]);
        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film2->id, 'position' => 2]);
        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film3->id, 'position' => 3]);

        $this->actingAs($user)
            ->deleteJson("/api/management/collections/{$collection->id}/films/{$film1->id}")
            ->assertOk();

        $this->assertDatabaseHas('collection_films', ['film_id' => $film2->id, 'position' => 1]);
        $this->assertDatabaseHas('collection_films', ['film_id' => $film3->id, 'position' => 2]);
    }

    public function test_destroy_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();
        $film = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film->id]);

        $this->actingAs($user)
            ->deleteJson("/api/management/collections/{$collection->id}/films/{$film->id}")
            ->assertForbidden();
    }

    // --- reorder ---

    public function test_reorder_updates_positions(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film1 = Film::factory()->create();
        $film2 = Film::factory()->create();
        $film3 = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film1->id, 'position' => 1]);
        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film2->id, 'position' => 2]);
        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film3->id, 'position' => 3]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}/films/reorder", [
                'films' => [$film3->id, $film1->id, $film2->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('collection_films', ['film_id' => $film3->id, 'position' => 1]);
        $this->assertDatabaseHas('collection_films', ['film_id' => $film1->id, 'position' => 2]);
        $this->assertDatabaseHas('collection_films', ['film_id' => $film2->id, 'position' => 3]);
    }

    public function test_reorder_fails_with_wrong_count(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film1 = Film::factory()->create();
        $film2 = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film1->id, 'position' => 1]);
        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film2->id, 'position' => 2]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}/films/reorder", [
                'films' => [$film1->id],
            ])
            ->assertUnprocessable();
    }

    public function test_reorder_fails_with_foreign_film(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);
        $film1 = Film::factory()->create();
        $foreign = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film1->id, 'position' => 1]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}/films/reorder", [
                'films' => [$foreign->id],
            ])
            ->assertUnprocessable();
    }

    public function test_reorder_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();
        $film = Film::factory()->create();

        CollectionFilm::factory()->create(['collection_id' => $collection->id, 'film_id' => $film->id, 'position' => 1]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}/films/reorder", [
                'films' => [$film->id],
            ])
            ->assertForbidden();
    }
}
