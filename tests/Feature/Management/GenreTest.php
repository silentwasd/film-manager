<?php

namespace Tests\Feature\Management;

use App\Enums\UserRole;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/genres')->assertUnauthorized();
    }

    public function test_index_returns_genres_to_any_authenticated_user(): void
    {
        $user = User::factory()->create();
        Genre::factory()->count(3)->create();

        $this->actingAs($user)
             ->getJson('/api/management/genres')
             ->assertOk()
             ->assertJsonCount(3, 'data');
    }

    // --- store (admin only) ---

    public function test_store_creates_genre_as_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
             ->postJson('/api/management/genres', ['name' => 'Action', 'slug' => 'action'])
             ->assertOk();

        $this->assertDatabaseHas('genres', ['slug' => 'action']);
    }

    public function test_store_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/genres', ['name' => 'Action', 'slug' => 'action'])
             ->assertForbidden();
    }

    public function test_store_fails_with_duplicate_slug(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Genre::factory()->create(['slug' => 'action']);

        $this->actingAs($admin)
             ->postJson('/api/management/genres', ['name' => 'Action 2', 'slug' => 'action'])
             ->assertUnprocessable();
    }

    // --- update (admin only) ---

    public function test_update_genre_as_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $genre = Genre::factory()->create();

        $this->actingAs($admin)
             ->putJson("/api/management/genres/{$genre->id}", [
                 'name' => 'Updated',
                 'slug' => 'updated',
             ])
             ->assertOk();

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => 'Updated']);
    }

    public function test_update_forbidden_for_regular_user(): void
    {
        $user  = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user)
             ->putJson("/api/management/genres/{$genre->id}", ['name' => 'Hacked', 'slug' => 'hacked'])
             ->assertForbidden();
    }

    // --- destroy (admin only) ---

    public function test_destroy_genre_as_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $genre = Genre::factory()->create();

        $this->actingAs($admin)
             ->deleteJson("/api/management/genres/{$genre->id}")
             ->assertOk();

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    public function test_destroy_forbidden_for_regular_user(): void
    {
        $user  = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user)
             ->deleteJson("/api/management/genres/{$genre->id}")
             ->assertForbidden();
    }
}
