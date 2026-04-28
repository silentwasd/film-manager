<?php

namespace Tests\Feature\Management;

use App\Enums\UserRole;
use App\Models\Film;
use App\Models\FilmPerson;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmPersonTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_returns_cast_for_film(): void
    {
        $user   = User::factory()->create();
        $film   = Film::factory()->create(['author_id' => $user->id]);
        $person = Person::factory()->create(['author_id' => $user->id]);
        FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->actingAs($user)
             ->getJson("/api/management/films/{$film->id}/persons")
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    // --- store ---

    public function test_store_adds_person_to_film(): void
    {
        $user   = User::factory()->create();
        $film   = Film::factory()->create(['author_id' => $user->id]);
        $person = Person::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->postJson("/api/management/films/{$film->id}/persons", [
                 'person_id' => $person->id,
                 'role'      => 'actor',
             ])
             ->assertOk();

        $this->assertDatabaseHas('film_people', ['film_id' => $film->id, 'person_id' => $person->id]);
    }

    public function test_store_requires_authentication(): void
    {
        $film   = Film::factory()->create();
        $person = Person::factory()->create();

        $this->postJson("/api/management/films/{$film->id}/persons", [
            'person_id' => $person->id,
            'role'      => 'actor',
        ])->assertUnauthorized();
    }

    public function test_store_forbidden_for_non_author(): void
    {
        $author = User::factory()->create();
        $other  = User::factory()->create();
        $film   = Film::factory()->create(['author_id' => $author->id]);
        $person = Person::factory()->create(['author_id' => $author->id]);

        $this->actingAs($other)
             ->postJson("/api/management/films/{$film->id}/persons", [
                 'person_id' => $person->id,
                 'role'      => 'actor',
             ])
             ->assertForbidden();
    }

    public function test_store_allowed_for_admin(): void
    {
        $admin  = User::factory()->create(['role' => UserRole::Admin]);
        $film   = Film::factory()->create();
        $person = Person::factory()->create();

        $this->actingAs($admin)
             ->postJson("/api/management/films/{$film->id}/persons", [
                 'person_id' => $person->id,
                 'role'      => 'director',
             ])
             ->assertOk();
    }

    // --- update ---

    public function test_update_film_person_by_film_author(): void
    {
        $user       = User::factory()->create();
        $film       = Film::factory()->create(['author_id' => $user->id]);
        $person     = Person::factory()->create(['author_id' => $user->id]);
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id, 'role' => 'actor']);

        $this->actingAs($user)
             ->putJson("/api/management/films/{$film->id}/persons/{$filmPerson->id}", [
                 'person_id' => $person->id,
                 'role'      => 'director',
             ])
             ->assertOk();

        $this->assertDatabaseHas('film_people', ['id' => $filmPerson->id, 'role' => 'director']);
    }

    public function test_update_forbidden_for_non_author(): void
    {
        $author     = User::factory()->create();
        $other      = User::factory()->create();
        $film       = Film::factory()->create(['author_id' => $author->id]);
        $person     = Person::factory()->create(['author_id' => $author->id]);
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->actingAs($other)
             ->putJson("/api/management/films/{$film->id}/persons/{$filmPerson->id}", [
                 'person_id' => $person->id,
                 'role'      => 'director',
             ])
             ->assertForbidden();
    }

    // --- destroy ---

    public function test_destroy_by_film_author(): void
    {
        $user       = User::factory()->create();
        $film       = Film::factory()->create(['author_id' => $user->id]);
        $person     = Person::factory()->create(['author_id' => $user->id]);
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->actingAs($user)
             ->deleteJson("/api/management/films/{$film->id}/persons/{$filmPerson->id}")
             ->assertOk();

        $this->assertDatabaseMissing('film_people', ['id' => $filmPerson->id]);
    }

    public function test_destroy_forbidden_for_non_author(): void
    {
        $author     = User::factory()->create();
        $other      = User::factory()->create();
        $film       = Film::factory()->create(['author_id' => $author->id]);
        $person     = Person::factory()->create(['author_id' => $author->id]);
        $filmPerson = FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->actingAs($other)
             ->deleteJson("/api/management/films/{$film->id}/persons/{$filmPerson->id}")
             ->assertForbidden();
    }
}
