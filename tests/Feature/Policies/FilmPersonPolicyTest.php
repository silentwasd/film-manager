<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Film;
use App\Models\FilmPerson;
use App\Models\Person;
use App\Models\User;
use App\Policies\FilmPersonPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmPersonPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FilmPersonPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FilmPersonPolicy();
    }

    // --- create ---

    public function test_film_author_can_add_person(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);

        $this->assertTrue($this->policy->create($user, $film));
    }

    public function test_non_author_cannot_add_person(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create();

        $this->assertFalse($this->policy->create($user, $film));
    }

    public function test_admin_can_add_person_to_any_film(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $film  = Film::factory()->create();

        $this->assertTrue($this->policy->create($admin, $film));
    }

    // --- update ---

    public function test_film_author_can_update_film_person(): void
    {
        $user       = User::factory()->create();
        $film       = Film::factory()->create(['author_id' => $user->id]);
        $person     = Person::factory()->create(['author_id' => $user->id]);
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->assertTrue($this->policy->update($user, $filmPerson));
    }

    public function test_non_author_cannot_update_film_person(): void
    {
        $user       = User::factory()->create();
        $film       = Film::factory()->create();
        $person     = Person::factory()->create();
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->assertFalse($this->policy->update($user, $filmPerson));
    }

    public function test_admin_can_update_any_film_person(): void
    {
        $admin      = User::factory()->create(['role' => UserRole::Admin]);
        $film       = Film::factory()->create();
        $person     = Person::factory()->create();
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->assertTrue($this->policy->update($admin, $filmPerson));
    }

    // --- delete ---

    public function test_film_author_can_delete_film_person(): void
    {
        $user       = User::factory()->create();
        $film       = Film::factory()->create(['author_id' => $user->id]);
        $person     = Person::factory()->create(['author_id' => $user->id]);
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->assertTrue($this->policy->delete($user, $filmPerson));
    }

    public function test_non_author_cannot_delete_film_person(): void
    {
        $user       = User::factory()->create();
        $film       = Film::factory()->create();
        $person     = Person::factory()->create();
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->assertFalse($this->policy->delete($user, $filmPerson));
    }

    public function test_admin_can_delete_any_film_person(): void
    {
        $admin      = User::factory()->create(['role' => UserRole::Admin]);
        $film       = Film::factory()->create();
        $person     = Person::factory()->create();
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->assertTrue($this->policy->delete($admin, $filmPerson));
    }
}
