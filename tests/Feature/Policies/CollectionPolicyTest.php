<?php

namespace Tests\Feature\Policies;

use App\Models\Collection;
use App\Models\User;
use App\Policies\CollectionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CollectionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CollectionPolicy;
    }

    // --- update ---

    public function test_owner_can_update(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $collection));
    }

    public function test_non_owner_cannot_update(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();

        $this->assertFalse($this->policy->update($user, $collection));
    }

    // --- delete ---

    public function test_owner_can_delete(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $collection));
    }

    public function test_non_owner_cannot_delete(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();

        $this->assertFalse($this->policy->delete($user, $collection));
    }

    // --- manageFilms ---

    public function test_owner_can_manage_films(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->manageFilms($user, $collection));
    }

    public function test_non_owner_cannot_manage_films(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();

        $this->assertFalse($this->policy->manageFilms($user, $collection));
    }
}
