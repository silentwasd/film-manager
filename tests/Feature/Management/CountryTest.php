<?php

namespace Tests\Feature\Management;

use App\Enums\UserRole;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/countries')->assertUnauthorized();
    }

    public function test_index_returns_countries(): void
    {
        $user = User::factory()->create();
        Country::factory()->count(2)->create();

        $this->actingAs($user)
             ->getJson('/api/management/countries')
             ->assertOk()
             ->assertJsonCount(2, 'data');
    }

    // --- store (admin only) ---

    public function test_store_creates_country_as_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
             ->postJson('/api/management/countries', ['name' => 'Japan'])
             ->assertOk();

        $this->assertDatabaseHas('countries', ['name' => 'Japan']);
    }

    public function test_store_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/countries', ['name' => 'Japan'])
             ->assertForbidden();
    }

    // --- update (admin only) ---

    public function test_update_country_as_admin(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $country = Country::factory()->create();

        $this->actingAs($admin)
             ->putJson("/api/management/countries/{$country->id}", ['name' => 'Updated'])
             ->assertOk();

        $this->assertDatabaseHas('countries', ['id' => $country->id, 'name' => 'Updated']);
    }

    public function test_update_forbidden_for_regular_user(): void
    {
        $user    = User::factory()->create();
        $country = Country::factory()->create();

        $this->actingAs($user)
             ->putJson("/api/management/countries/{$country->id}", ['name' => 'Hacked'])
             ->assertForbidden();
    }

    // --- destroy (admin only) ---

    public function test_destroy_country_as_admin(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $country = Country::factory()->create();

        $this->actingAs($admin)
             ->deleteJson("/api/management/countries/{$country->id}")
             ->assertOk();

        $this->assertDatabaseMissing('countries', ['id' => $country->id]);
    }

    public function test_destroy_forbidden_for_regular_user(): void
    {
        $user    = User::factory()->create();
        $country = Country::factory()->create();

        $this->actingAs($user)
             ->deleteJson("/api/management/countries/{$country->id}")
             ->assertForbidden();
    }
}
