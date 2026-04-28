<?php

namespace Tests\Feature\Management;

use App\Enums\UserRole;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/tags')->assertUnauthorized();
    }

    public function test_index_returns_tags(): void
    {
        $user = User::factory()->create();
        Tag::factory()->count(2)->create();

        $this->actingAs($user)
             ->getJson('/api/management/tags')
             ->assertOk()
             ->assertJsonCount(2, 'data');
    }

    // --- store (admin only) ---

    public function test_store_creates_tag_as_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
             ->postJson('/api/management/tags', ['name' => 'Award Winner'])
             ->assertOk();

        $this->assertDatabaseHas('tags', ['name' => 'Award Winner']);
    }

    public function test_store_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/tags', ['name' => 'Award Winner'])
             ->assertForbidden();
    }

    // --- update (admin only) ---

    public function test_update_tag_as_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $tag   = Tag::factory()->create();

        $this->actingAs($admin)
             ->putJson("/api/management/tags/{$tag->id}", ['name' => 'Updated'])
             ->assertOk();

        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Updated']);
    }

    public function test_update_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();
        $tag  = Tag::factory()->create();

        $this->actingAs($user)
             ->putJson("/api/management/tags/{$tag->id}", ['name' => 'Hacked'])
             ->assertForbidden();
    }

    // --- destroy (admin only) ---

    public function test_destroy_tag_as_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $tag   = Tag::factory()->create();

        $this->actingAs($admin)
             ->deleteJson("/api/management/tags/{$tag->id}")
             ->assertOk();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_destroy_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();
        $tag  = Tag::factory()->create();

        $this->actingAs($user)
             ->deleteJson("/api/management/tags/{$tag->id}")
             ->assertForbidden();
    }
}
