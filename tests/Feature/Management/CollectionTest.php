<?php

namespace Tests\Feature\Management;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/collections')->assertUnauthorized();
    }

    public function test_index_returns_only_user_collections(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Collection::factory()->create(['user_id' => $user->id]);
        Collection::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->getJson('/api/management/collections')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_returns_films_count(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/management/collections')
            ->assertOk()
            ->assertJsonPath('data.0.films_count', 0);
    }

    // --- show ---

    public function test_show_requires_authentication(): void
    {
        $collection = Collection::factory()->create();

        $this->getJson("/api/management/collections/{$collection->id}")->assertUnauthorized();
    }

    public function test_show_returns_collection_with_films(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$collection->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $collection->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'films']]);
    }

    public function test_show_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$collection->id}")
            ->assertForbidden();
    }

    // --- store ---

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/management/collections', ['name' => 'Любимые'])->assertUnauthorized();
    }

    public function test_store_creates_collection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', ['name' => 'Лучшие боевики'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Лучшие боевики');

        $this->assertDatabaseHas('collections', ['user_id' => $user->id, 'name' => 'Лучшие боевики']);
    }

    public function test_store_fails_without_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', [])
            ->assertUnprocessable();
    }

    public function test_store_fails_with_name_too_long(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', ['name' => str_repeat('а', 256)])
            ->assertUnprocessable();
    }

    // --- update ---

    public function test_update_requires_authentication(): void
    {
        $collection = Collection::factory()->create();

        $this->putJson("/api/management/collections/{$collection->id}", ['name' => 'Новое'])->assertUnauthorized();
    }

    public function test_update_changes_name(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", ['name' => 'Обновлённое'])
            ->assertOk();

        $this->assertDatabaseHas('collections', ['id' => $collection->id, 'name' => 'Обновлённое']);
    }

    public function test_update_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", ['name' => 'Чужое'])
            ->assertForbidden();
    }

    // --- destroy ---

    public function test_destroy_requires_authentication(): void
    {
        $collection = Collection::factory()->create();

        $this->deleteJson("/api/management/collections/{$collection->id}")->assertUnauthorized();
    }

    public function test_destroy_deletes_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/management/collections/{$collection->id}")
            ->assertOk();

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }

    public function test_destroy_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/management/collections/{$collection->id}")
            ->assertForbidden();
    }

    // --- публикация ---

    public function test_store_creates_private_collection_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', ['name' => 'Боевики'])
            ->assertCreated()
            ->assertJsonPath('data.is_public', false);

        $this->assertDatabaseHas('collections', ['name' => 'Боевики', 'is_public' => false]);
    }

    public function test_store_creates_public_collection_with_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', [
                'name' => 'Лучшие боевики',
                'description' => 'Подборка на вечер',
                'is_public' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.description', 'Подборка на вечер');

        $this->assertDatabaseHas('collections', ['name' => 'Лучшие боевики', 'is_public' => true]);
    }

    public function test_update_publishes_private_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id, 'is_public' => false]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", [
                'name' => $collection->name,
                'is_public' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_public', true);

        $this->assertDatabaseHas('collections', ['id' => $collection->id, 'is_public' => true]);
    }

    public function test_update_unpublishes_public_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", [
                'name' => $collection->name,
                'is_public' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_public', false);

        $this->assertDatabaseHas('collections', ['id' => $collection->id, 'is_public' => false]);
    }

    public function test_update_keeps_visibility_when_flag_is_omitted(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", ['name' => 'Новое имя'])
            ->assertOk()
            ->assertJsonPath('data.is_public', true);
    }

    public function test_publish_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['is_public' => false]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", [
                'name' => $collection->name,
                'is_public' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('collections', ['id' => $collection->id, 'is_public' => false]);
    }

    public function test_public_url_is_exposed_only_for_published_collections(): void
    {
        $user = User::factory()->create();
        $private = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Скрытая']);
        $published = Collection::factory()->public()->create(['user_id' => $user->id, 'name' => 'Открытая']);

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$private->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.public_url');

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$published->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.public_url',
                config('app.frontend_url').'/collections/'.$published->id.'-otkrytaya'
            );
    }

    public function test_description_too_long_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', [
                'name' => 'Боевики',
                'description' => str_repeat('а', 2001),
            ])
            ->assertUnprocessable();
    }
}
