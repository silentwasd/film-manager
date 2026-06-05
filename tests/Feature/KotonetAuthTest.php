<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class KotonetAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kotonet.client_id' => 'test-client-id',
            'services.kotonet.client_secret' => 'test-client-secret',
            'services.kotonet.redirect' => 'http://localhost/auth/callback',
            'services.kotonet.base_url' => 'https://id.kotonet.test',
        ]);
    }

    // --- redirect ---

    public function test_redirect_redirects_to_kotonet_authorize(): void
    {
        $response = $this->get('/auth/kotonet');

        $response->assertRedirectContains(config('services.kotonet.base_url').'/oauth/authorize');
    }

    public function test_redirect_includes_required_query_params(): void
    {
        $response = $this->get('/auth/kotonet');

        $location = $response->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $params);

        $this->assertSame(config('services.kotonet.client_id'), $params['client_id']);
        $this->assertSame(config('services.kotonet.redirect'), $params['redirect_uri']);
        $this->assertSame('code', $params['response_type']);
        $this->assertNotEmpty($params['state']);
    }

    public function test_redirect_stores_state_in_cache(): void
    {
        $response = $this->get('/auth/kotonet');

        $location = $response->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $params);

        $this->assertTrue(Cache::has("kotonet_state_{$params['state']}"));
    }

    // --- callback ---

    public function test_callback_creates_new_user_and_redirects_with_token(): void
    {
        $state = $this->storeState();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'test-access-token']),
            '*/api/user' => Http::response(['id' => 'kotonet-123', 'name' => 'Иван', 'email' => 'ivan@example.com']),
        ]);

        $response = $this->get("/auth/callback?code=authcode&state={$state}");

        $response->assertRedirectContains('/auth/callback?token=');
        $this->assertDatabaseHas('users', ['kotonet_id' => 'kotonet-123', 'name' => 'Иван']);
    }

    public function test_callback_updates_existing_user(): void
    {
        $user = User::factory()->create(['kotonet_id' => 'kotonet-123', 'name' => 'Старое имя']);
        $state = $this->storeState();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'test-access-token']),
            '*/api/user' => Http::response(['id' => 'kotonet-123', 'name' => 'Новое имя', 'email' => $user->email]),
        ]);

        $this->get("/auth/callback?code=authcode&state={$state}");

        $this->assertDatabaseHas('users', ['id' => $user->id, 'kotonet_id' => 'kotonet-123', 'name' => 'Новое имя']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_callback_links_kotonet_id_to_existing_user_by_email(): void
    {
        $user = User::factory()->create(['kotonet_id' => null, 'email' => 'ivan@example.com']);
        $state = $this->storeState();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'test-access-token']),
            '*/api/user' => Http::response(['id' => 'kotonet-123', 'name' => 'Иван', 'email' => 'ivan@example.com']),
        ]);

        $this->get("/auth/callback?code=authcode&state={$state}");

        $this->assertDatabaseHas('users', ['id' => $user->id, 'kotonet_id' => 'kotonet-123']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_callback_state_is_consumed_after_use(): void
    {
        $state = $this->storeState();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'test-access-token']),
            '*/api/user' => Http::response(['id' => 'kotonet-123', 'name' => 'Иван', 'email' => 'ivan@example.com']),
        ]);

        $this->get("/auth/callback?code=authcode&state={$state}");

        $this->assertFalse(Cache::has("kotonet_state_{$state}"));
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $this->storeState();

        $response = $this->get('/auth/callback?code=authcode&state=invalid-state');

        $response->assertStatus(422);
    }

    public function test_callback_rejects_missing_state(): void
    {
        $response = $this->get('/auth/callback?code=authcode');

        $response->assertStatus(422);
    }

    public function test_callback_redirects_to_frontend_on_oauth_error(): void
    {
        $response = $this->get('/auth/callback?error=access_denied&state=anything');

        $response->assertRedirect(config('app.frontend_url'));
    }

    private function storeState(): string
    {
        $state = Str::random(40);
        Cache::put("kotonet_state_{$state}", true, now()->addMinutes(5));

        return $state;
    }
}
