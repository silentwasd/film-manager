<?php

namespace Tests\Feature\Management;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);

        $this->actingAs($user)
             ->getJson('/api/management/profile')
             ->assertOk()
             ->assertJsonPath('data.name', 'Alice');
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/management/profile')->assertUnauthorized();
    }
}
