<?php

namespace Tests\Feature\Public;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_profile_without_authentication(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);

        $this->getJson('/api/users/'.$user->id.'-ivan')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Иван');
    }

    public function test_show_lists_public_and_personal_collections_only(): void
    {
        $user = User::factory()->create();

        Collection::factory()->public()->create(['user_id' => $user->id, 'name' => 'Открытая']);
        Collection::factory()->personal()->create(['user_id' => $user->id, 'name' => 'Личная']);
        Collection::factory()->hidden()->create(['user_id' => $user->id, 'name' => 'Скрытая']);

        $response = $this->getJson('/api/users/'.$user->public_key)
            ->assertOk()
            ->assertJsonCount(2, 'data.collections');

        $names = array_column($response->json('data.collections'), 'name');

        $this->assertContains('Открытая', $names);
        $this->assertContains('Личная', $names);
        $this->assertNotContains('Скрытая', $names);
    }

    public function test_show_hides_hidden_collections_even_from_their_owner(): void
    {
        $user = User::factory()->create();
        Collection::factory()->hidden()->create(['user_id' => $user->id, 'name' => 'Скрытая']);

        $this->actingAs($user)
            ->getJson('/api/users/'.$user->public_key)
            ->assertOk()
            ->assertJsonCount(0, 'data.collections')
            ->assertDontSee('Скрытая');
    }

    public function test_show_does_not_list_collections_of_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Collection::factory()->public()->create(['user_id' => $user->id, 'name' => 'Своя']);
        Collection::factory()->public()->create(['user_id' => $other->id, 'name' => 'Чужая']);

        $this->getJson('/api/users/'.$user->public_key)
            ->assertOk()
            ->assertJsonCount(1, 'data.collections')
            ->assertJsonPath('data.collections.0.name', 'Своя');
    }

    public function test_show_gives_each_collection_the_path_matching_its_visibility(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);

        $public = Collection::factory()->public()->create(['user_id' => $user->id, 'name' => 'Открытая']);
        $personal = Collection::factory()->personal()->create(['user_id' => $user->id, 'name' => 'Личная']);

        $paths = collect(
            $this->getJson('/api/users/'.$user->public_key)->assertOk()->json('data.collections')
        )->pluck('path', 'name');

        $this->assertSame('/collections/'.$public->id.'-otkrytaya', $paths['Открытая']);
        $this->assertSame(
            '/users/'.$user->id.'-ivan/collections/'.$personal->id.'-lichnaya',
            $paths['Личная']
        );
    }

    public function test_show_returns_films_count_per_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create(['user_id' => $user->id]);

        \App\Models\CollectionFilm::factory()->count(3)->create(['collection_id' => $collection->id]);

        $this->getJson('/api/users/'.$user->public_key)
            ->assertOk()
            ->assertJsonPath('data.collections.0.films_count', 3);
    }

    public function test_show_does_not_leak_email_or_role(): void
    {
        $user = User::factory()->create(['email' => 'secret@example.com']);

        $response = $this->getJson('/api/users/'.$user->public_key)->assertOk();

        $response->assertDontSee('secret@example.com');
        $this->assertArrayNotHasKey('email', $response->json('data'));
        $this->assertArrayNotHasKey('role', $response->json('data'));
    }

    public function test_show_returns_404_for_unknown_user(): void
    {
        $this->getJson('/api/users/999999-net-takogo')->assertNotFound();
    }

    public function test_show_returns_404_for_key_without_leading_id(): void
    {
        User::factory()->create(['name' => 'Иван']);

        $this->getJson('/api/users/ivan')->assertNotFound();
    }

    public function test_show_ignores_outdated_slug(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);

        $this->getJson('/api/users/'.$user->id.'-sovsem-drugoj-slag')
            ->assertOk()
            ->assertJsonPath('data.name', 'Иван');
    }

    public function test_profile_of_user_without_visible_collections_is_still_reachable(): void
    {
        $user = User::factory()->create(['name' => 'Пустой']);

        $this->getJson('/api/users/'.$user->public_key)
            ->assertOk()
            ->assertJsonCount(0, 'data.collections');
    }
}
