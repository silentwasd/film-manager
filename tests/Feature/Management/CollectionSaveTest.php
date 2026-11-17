<?php

namespace Tests\Feature\Management;

use App\Models\Collection;
use App\Models\CollectionFilm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_requires_authentication(): void
    {
        $collection = Collection::factory()->public()->create();

        $this->postJson("/api/management/collections/{$collection->id}/save")->assertUnauthorized();
    }

    public function test_user_can_save_public_and_personal_collections(): void
    {
        $user = User::factory()->create();
        $public = Collection::factory()->public()->create();
        $personal = Collection::factory()->personal()->create();

        $this->actingAs($user)->postJson("/api/management/collections/{$public->id}/save")->assertOk();
        $this->actingAs($user)->postJson("/api/management/collections/{$personal->id}/save")->assertOk();

        $this->assertDatabaseCount('collection_saves', 2);
    }

    public function test_save_is_idempotent(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create();

        $this->actingAs($user)->postJson("/api/management/collections/{$collection->id}/save")->assertOk();
        $this->actingAs($user)->postJson("/api/management/collections/{$collection->id}/save")->assertOk();

        $this->assertDatabaseCount('collection_saves', 1);
    }

    public function test_hidden_collection_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->hidden()->create();

        $this->actingAs($user)
            ->postJson("/api/management/collections/{$collection->id}/save")
            ->assertNotFound();

        $this->assertDatabaseCount('collection_saves', 0);
    }

    public function test_own_collection_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/management/collections/{$collection->id}/save")
            ->assertStatus(422);
    }

    public function test_index_lists_own_and_saved_collections(): void
    {
        $user = User::factory()->create();
        $author = User::factory()->create(['name' => 'Автор']);

        $own = Collection::factory()->create(['user_id' => $user->id]);
        $saved = Collection::factory()->public()->create(['user_id' => $author->id]);
        Collection::factory()->public()->create(['user_id' => $author->id]);

        $user->savedCollections()->attach($saved);

        $response = $this->actingAs($user)
            ->getJson('/api/management/collections?sort_column=id&sort_direction=asc')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.is_own', true)
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.1.id', $saved->id)
            ->assertJsonPath('data.1.is_own', false)
            ->assertJsonPath('data.1.can_edit', false)
            ->assertJsonPath('data.1.author.name', 'Автор');

        $this->assertArrayNotHasKey('author', $response->json('data.0'));
    }

    public function test_index_scope_filters_own_and_saved(): void
    {
        $user = User::factory()->create();
        $own = Collection::factory()->create(['user_id' => $user->id]);
        $saved = Collection::factory()->public()->create();
        $user->savedCollections()->attach($saved);

        $this->actingAs($user)
            ->getJson('/api/management/collections?scope=own')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->actingAs($user)
            ->getJson('/api/management/collections?scope=saved')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $saved->id);
    }

    public function test_saved_collection_disappears_when_author_hides_it(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create();
        $user->savedCollections()->attach($collection);

        $collection->update(['visibility' => 'hidden']);

        $this->actingAs($user)
            ->getJson('/api/management/collections')
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$collection->id}")
            ->assertForbidden();
    }

    public function test_search_by_model_id_does_not_leak_foreign_collections(): void
    {
        $user = User::factory()->create();
        Collection::factory()->create(['user_id' => $user->id, 'name' => 'Боевики']);
        $foreign = Collection::factory()->public()->create(['name' => 'Чужая']);

        $this->actingAs($user)
            ->getJson("/api/management/collections?name=Боев&model_id={$foreign->id}")
            ->assertJsonCount(1, 'data');
    }

    public function test_saved_collection_can_be_viewed_but_not_edited(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->personal()->create();
        $film = CollectionFilm::factory()->create(['collection_id' => $collection->id, 'position' => 1]);
        $user->savedCollections()->attach($collection);

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$collection->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonCount(1, 'data.films');

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", ['name' => 'Моё'])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson("/api/management/collections/{$collection->id}/films/{$film->film_id}")
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson("/api/management/collections/{$collection->id}")
            ->assertForbidden();
    }

    public function test_unsaved_foreign_collection_cannot_be_viewed_in_management(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create();

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$collection->id}")
            ->assertForbidden();
    }

    public function test_unsave_removes_only_own_bookmark(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $collection = Collection::factory()->public()->create();

        $user->savedCollections()->attach($collection);
        $other->savedCollections()->attach($collection);

        $this->actingAs($user)
            ->deleteJson("/api/management/collections/{$collection->id}/save")
            ->assertOk()
            ->assertJsonPath('saved', false);

        $this->assertDatabaseHas('collections', ['id' => $collection->id]);
        $this->assertDatabaseMissing('collection_saves', ['user_id' => $user->id]);
        $this->assertDatabaseHas('collection_saves', ['user_id' => $other->id]);
    }

    public function test_public_page_reports_viewer_state(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create();

        $this->getJson("/api/collections/{$collection->public_key}")
            ->assertOk()
            ->assertJsonMissingPath('data.viewer');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/collections/{$collection->public_key}")
            ->assertJsonPath('data.viewer.own', false)
            ->assertJsonPath('data.viewer.saved', false);

        $user->savedCollections()->attach($collection);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/collections/{$collection->public_key}")
            ->assertJsonPath('data.viewer.saved', true);
    }
}
