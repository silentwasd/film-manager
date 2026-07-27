<?php

namespace Tests\Feature\Management;

use App\Enums\CollectionVisibility;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    // --- видимость ---

    public function test_store_creates_hidden_collection_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', ['name' => 'Боевики'])
            ->assertCreated()
            ->assertJsonPath('data.visibility', CollectionVisibility::Hidden->value);

        $this->assertDatabaseHas('collections', [
            'name' => 'Боевики',
            'visibility' => CollectionVisibility::Hidden->value,
        ]);
    }

    public function test_store_creates_public_collection_with_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', [
                'name' => 'Лучшие боевики',
                'description' => 'Подборка на вечер',
                'visibility' => CollectionVisibility::Public->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.visibility', CollectionVisibility::Public->value)
            ->assertJsonPath('data.description', 'Подборка на вечер');
    }

    public function test_store_creates_personal_collection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/management/collections', [
                'name' => 'Любимое',
                'visibility' => CollectionVisibility::Personal->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.visibility', CollectionVisibility::Personal->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function visibilityProvider(): array
    {
        return [
            'public' => [CollectionVisibility::Public->value],
            'personal' => [CollectionVisibility::Personal->value],
            'hidden' => [CollectionVisibility::Hidden->value],
        ];
    }

    #[DataProvider('visibilityProvider')]
    public function test_update_switches_visibility_to(string $visibility): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->personal()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", [
                'name' => $collection->name,
                'visibility' => $visibility,
            ])
            ->assertOk()
            ->assertJsonPath('data.visibility', $visibility);

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'visibility' => $visibility,
        ]);
    }

    public function test_update_keeps_visibility_when_field_is_omitted(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", ['name' => 'Новое имя'])
            ->assertOk()
            ->assertJsonPath('data.visibility', CollectionVisibility::Public->value);
    }

    public function test_update_rejects_unknown_visibility(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", [
                'name' => $collection->name,
                'visibility' => 'everyone',
            ])
            ->assertUnprocessable();
    }

    public function test_changing_visibility_forbidden_for_other_user(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->hidden()->create();

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", [
                'name' => $collection->name,
                'visibility' => CollectionVisibility::Public->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'visibility' => CollectionVisibility::Hidden->value,
        ]);
    }

    public function test_public_url_points_to_short_address_for_public_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create(['user_id' => $user->id, 'name' => 'Открытая']);

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$collection->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.public_url',
                config('app.frontend_url').'/collections/'.$collection->id.'-otkrytaya'
            );
    }

    public function test_public_url_points_inside_profile_for_personal_collection(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);
        $collection = Collection::factory()->personal()->create(['user_id' => $user->id, 'name' => 'Личная']);

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$collection->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.public_url',
                config('app.frontend_url').'/users/'.$user->id.'-ivan/collections/'.$collection->id.'-lichnaya'
            );
    }

    public function test_public_url_is_absent_for_hidden_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->hidden()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/management/collections/{$collection->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.public_url');
    }

    public function test_public_url_follows_visibility_change_in_index(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);
        $collection = Collection::factory()->personal()->create(['user_id' => $user->id, 'name' => 'Подборка']);

        $this->actingAs($user)
            ->getJson('/api/management/collections')
            ->assertOk()
            ->assertJsonPath(
                'data.0.public_url',
                config('app.frontend_url').'/users/'.$user->id.'-ivan/collections/'.$collection->id.'-podborka'
            );

        $this->actingAs($user)
            ->putJson("/api/management/collections/{$collection->id}", [
                'name' => 'Подборка',
                'visibility' => CollectionVisibility::Public->value,
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.public_url',
                config('app.frontend_url').'/collections/'.$collection->id.'-podborka'
            );
    }

    public function test_index_returns_collections_of_every_visibility(): void
    {
        $user = User::factory()->create();

        Collection::factory()->public()->create(['user_id' => $user->id]);
        Collection::factory()->personal()->create(['user_id' => $user->id]);
        Collection::factory()->hidden()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/management/collections')
            ->assertOk()
            ->assertJsonCount(3, 'data');
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
