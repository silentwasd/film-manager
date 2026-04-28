<?php

namespace Tests\Feature\Management;

use App\Enums\UserRole;
use App\Models\Film;
use App\Models\FilmPerson;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use RefreshDatabase;

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/management/people')->assertUnauthorized();
    }

    public function test_index_returns_people(): void
    {
        $user = User::factory()->create();
        Person::factory()->count(2)->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->getJson('/api/management/people')
             ->assertOk()
             ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_name(): void
    {
        $user = User::factory()->create();
        Person::factory()->create(['author_id' => $user->id, 'name' => 'Keanu Reeves']);
        Person::factory()->create(['author_id' => $user->id, 'name' => 'Brad Pitt']);

        $response = $this->actingAs($user)->getJson('/api/management/people?name=Keanu');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Keanu Reeves', $response->json('data.0.name'));
    }

    // --- store ---

    public function test_store_creates_person(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/management/people', ['name' => 'John Doe'])
             ->assertOk();

        $this->assertDatabaseHas('people', ['name' => 'John Doe', 'author_id' => $user->id]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/management/people', ['name' => 'John'])->assertUnauthorized();
    }

    public function test_store_fails_without_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/management/people', [])->assertUnprocessable();
    }

    // --- show ---

    public function test_show_returns_person(): void
    {
        $person = Person::factory()->create(['name' => 'Jane Doe']);

        $this->getJson("/api/management/people/{$person->id}")
             ->assertOk()
             ->assertJsonPath('data.name', 'Jane Doe');
    }

    public function test_show_is_public(): void
    {
        $person = Person::factory()->create();

        $this->getJson("/api/management/people/{$person->id}")->assertOk();
    }

    // --- update ---

    public function test_update_by_author(): void
    {
        $user   = User::factory()->create();
        $person = Person::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->putJson("/api/management/people/{$person->id}", ['name' => 'Updated Name'])
             ->assertOk();

        $this->assertDatabaseHas('people', ['id' => $person->id, 'name' => 'Updated Name']);
    }

    public function test_update_by_admin(): void
    {
        $admin  = User::factory()->create(['role' => UserRole::Admin]);
        $person = Person::factory()->create();

        $this->actingAs($admin)
             ->putJson("/api/management/people/{$person->id}", ['name' => 'Admin Edit'])
             ->assertOk();
    }

    public function test_update_forbidden_for_non_author(): void
    {
        $author = User::factory()->create();
        $other  = User::factory()->create();
        $person = Person::factory()->create(['author_id' => $author->id]);

        $this->actingAs($other)
             ->putJson("/api/management/people/{$person->id}", ['name' => 'Hacked'])
             ->assertForbidden();
    }

    // --- destroy ---

    public function test_destroy_by_author(): void
    {
        $user   = User::factory()->create();
        $person = Person::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->deleteJson("/api/management/people/{$person->id}")
             ->assertOk();

        $this->assertDatabaseMissing('people', ['id' => $person->id]);
    }

    public function test_destroy_by_admin(): void
    {
        $admin  = User::factory()->create(['role' => UserRole::Admin]);
        $person = Person::factory()->create();

        $this->actingAs($admin)
             ->deleteJson("/api/management/people/{$person->id}")
             ->assertOk();
    }

    public function test_destroy_forbidden_for_non_author(): void
    {
        $author = User::factory()->create();
        $other  = User::factory()->create();
        $person = Person::factory()->create(['author_id' => $author->id]);

        $this->actingAs($other)
             ->deleteJson("/api/management/people/{$person->id}")
             ->assertForbidden();
    }

    public function test_destroy_forbidden_when_person_has_films(): void
    {
        $author = User::factory()->create();
        $person = Person::factory()->create(['author_id' => $author->id]);
        $film   = Film::factory()->create(['author_id' => $author->id]);
        FilmPerson::factory()->create(['film_id' => $film->id, 'person_id' => $person->id]);

        $this->actingAs($author)
             ->deleteJson("/api/management/people/{$person->id}")
             ->assertForbidden();
    }
}
