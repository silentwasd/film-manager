<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Всё, на что смотрит коннектор, прежде чем предложить пользователю кнопку
 * «Connect»: метаданные ресурса и authorization server, динамическая
 * регистрация клиента и отказ без токена.
 */
class OauthDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_resource_metadata_points_at_authorization_server(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJson([
                'resource' => url('/mcp'),
                'authorization_servers' => [url('/')],
                'scopes_supported' => ['mcp:use'],
            ]);
    }

    public function test_authorization_server_metadata_advertises_pkce_and_dcr(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJson([
                'issuer' => url('/'),
                'authorization_endpoint' => route('passport.authorizations.authorize'),
                'token_endpoint' => route('passport.token'),
                'registration_endpoint' => url('/oauth/register'),
                'code_challenge_methods_supported' => ['S256'],
                'grant_types_supported' => ['authorization_code', 'refresh_token'],
            ]);
    }

    public function test_mcp_endpoint_rejects_anonymous_request_with_discovery_hint(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $response->assertUnauthorized();

        $this->assertStringContainsString(
            url('/.well-known/oauth-protected-resource/mcp'),
            (string) $response->headers->get('WWW-Authenticate'),
            'Без этой подсказки коннектор не найдёт, где авторизоваться.'
        );
    }

    public function test_dynamic_client_registration_accepts_known_connector(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ])
            ->assertCreated()
            ->assertJson([
                'grant_types' => ['authorization_code', 'refresh_token'],
                'response_types' => ['code'],
                'scope' => 'mcp:use',
                'token_endpoint_auth_method' => 'none',
            ])
            ->assertJsonStructure(['client_id']);
    }

    public function test_dynamic_client_registration_rejects_foreign_redirect_domain(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Кто-то посторонний',
            'redirect_uris' => ['https://evil.example/callback'],
        ])
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_redirect_uri']);
    }

    public function test_authorize_endpoint_sends_guest_to_login(): void
    {
        $clientId = $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ])->json('client_id');

        $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '=');

        $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))->assertRedirect(route('login'));
    }

    public function test_login_form_authenticates_user(): void
    {
        $user = User::factory()->create([
            'email' => 'connector@example.com',
            'password' => 'devpassword',
        ]);

        $this->post('/login', [
            'email' => 'connector@example.com',
            'password' => 'devpassword',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_refuses_redirect_to_foreign_host(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout', ['redirect_to' => 'https://evil.example/steal'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
