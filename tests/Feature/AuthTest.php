<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/api/register', [
            'name'                  => 'Another User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable();
    }

    public function test_register_fails_with_password_mismatch(): void
    {
        $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different123',
        ])->assertUnprocessable();
    }

    public function test_register_fails_with_short_name(): void
    {
        $this->postJson('/api/register', [
            'name'                  => 'ab',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable();
    }

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'wrongpassword',
        ])->assertNotFound();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password123',
        ])->assertNotFound();
    }

    public function test_login_fails_validation_with_missing_fields(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable();
    }
}
