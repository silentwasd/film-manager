<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Film;
use App\Models\FilmPerson;
use App\Models\Person;
use App\Models\User;
use App\Policies\PersonPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PersonPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PersonPolicy();
    }

    // --- update ---

    public function test_author_can_update(): void
    {
        $user   = User::factory()->create();
        $person = Person::factory()->create(['author_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $person));
    }

    public function test_non_author_cannot_update(): void
    {
        $user   = User::factory()->create();
        $person = Person::factory()->create();

        $this->assertFalse($this->policy->update($user, $person));
    }

    public function test_admin_can_update_any_person(): void
    {
        $admin  = User::factory()->create(['role' => UserRole::Admin]);
        $person = Person::factory()->create();

        $this->assertTrue($this->policy->update($admin, $person));
    }

    // --- delete ---

    public function test_author_can_delete_person_without_films(): void
    {
        $user   = User::factory()->create();
        $person = Person::factory()->create(['author_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $person));
    }

    public function test_author_cannot_delete_person_linked_to_films(): void
    {
        $user   = User::factory()->create();
        $person = Person::factory()->create(['author_id' => $user->id]);
        $film   = Film::factory()->create(['author_id' => $user->id]);
        FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->assertFalse($this->policy->delete($user, $person));
    }

    public function test_non_author_cannot_delete(): void
    {
        $user   = User::factory()->create();
        $person = Person::factory()->create();

        $this->assertFalse($this->policy->delete($user, $person));
    }

    public function test_admin_can_delete_any_person(): void
    {
        $admin  = User::factory()->create(['role' => UserRole::Admin]);
        $person = Person::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $person));
    }
}
