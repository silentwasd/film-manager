<?php

namespace Tests\Feature\Management;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Film;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/companies')->assertUnauthorized();
    }

    public function test_index_returns_companies(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(2)->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->getJson('/api/management/companies')
             ->assertOk()
             ->assertJsonCount(2, 'data');
    }

    // --- store ---

    public function test_store_creates_company(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/companies', ['name' => 'Warner Bros'])
             ->assertOk();

        $this->assertDatabaseHas('companies', ['name' => 'Warner Bros', 'author_id' => $user->id]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/management/companies', ['name' => 'Sony'])->assertUnauthorized();
    }

    // --- show ---

    public function test_show_returns_company(): void
    {
        $company = Company::factory()->create(['name' => 'Universal']);

        $this->getJson("/api/management/companies/{$company->id}")
             ->assertOk()
             ->assertJsonPath('data.name', 'Universal');
    }

    public function test_show_is_public(): void
    {
        $company = Company::factory()->create();

        $this->getJson("/api/management/companies/{$company->id}")->assertOk();
    }

    // --- update ---

    public function test_update_by_author(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->putJson("/api/management/companies/{$company->id}", ['name' => 'Renamed'])
             ->assertOk();

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Renamed']);
    }

    public function test_update_by_admin(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $company = Company::factory()->create();

        $this->actingAs($admin)
             ->putJson("/api/management/companies/{$company->id}", ['name' => 'Admin Renamed'])
             ->assertOk();
    }

    public function test_update_forbidden_for_non_author(): void
    {
        $author  = User::factory()->create();
        $other   = User::factory()->create();
        $company = Company::factory()->create(['author_id' => $author->id]);

        $this->actingAs($other)
             ->putJson("/api/management/companies/{$company->id}", ['name' => 'Hacked'])
             ->assertForbidden();
    }

    // --- destroy ---

    public function test_destroy_by_author(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->deleteJson("/api/management/companies/{$company->id}")
             ->assertOk();

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_destroy_by_admin(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $company = Company::factory()->create();

        $this->actingAs($admin)
             ->deleteJson("/api/management/companies/{$company->id}")
             ->assertOk();
    }

    public function test_destroy_forbidden_for_non_author(): void
    {
        $author  = User::factory()->create();
        $other   = User::factory()->create();
        $company = Company::factory()->create(['author_id' => $author->id]);

        $this->actingAs($other)
             ->deleteJson("/api/management/companies/{$company->id}")
             ->assertForbidden();
    }

    public function test_destroy_forbidden_when_company_has_films(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create(['author_id' => $user->id]);
        $film    = Film::factory()->create(['author_id' => $user->id]);
        $film->companies()->attach($company);

        $this->actingAs($user)
             ->deleteJson("/api/management/companies/{$company->id}")
             ->assertForbidden();
    }
}
