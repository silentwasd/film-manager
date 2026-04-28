<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Film;
use App\Models\User;
use App\Policies\CompanyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CompanyPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CompanyPolicy();
    }

    // --- update ---

    public function test_author_can_update(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create(['author_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $company));
    }

    public function test_non_author_cannot_update(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create();

        $this->assertFalse($this->policy->update($user, $company));
    }

    public function test_admin_can_update_any_company(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $company = Company::factory()->create();

        $this->assertTrue($this->policy->update($admin, $company));
    }

    // --- delete ---

    public function test_author_can_delete_company_without_films(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create(['author_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $company));
    }

    public function test_author_cannot_delete_company_with_films(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create(['author_id' => $user->id]);
        $film    = Film::factory()->create(['author_id' => $user->id]);
        $film->companies()->attach($company);

        $this->assertFalse($this->policy->delete($user, $company));
    }

    public function test_non_author_cannot_delete(): void
    {
        $user    = User::factory()->create();
        $company = Company::factory()->create();

        $this->assertFalse($this->policy->delete($user, $company));
    }

    public function test_admin_can_delete_any_company(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $company = Company::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $company));
    }
}
