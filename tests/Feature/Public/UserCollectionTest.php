<?php

namespace Tests\Feature\Public;

use App\Enums\CollectionVisibility;
use App\Models\Collection;
use App\Models\CollectionFilm;
use App\Models\Film;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCollectionTest extends TestCase
{
    use RefreshDatabase;

    private function url(User $user, Collection $collection): string
    {
        return '/api/users/'.$user->public_key.'/collections/'.$collection->public_key;
    }

    public function test_show_returns_personal_collection_without_authentication(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);
        $collection = Collection::factory()->personal()->create([
            'user_id' => $user->id,
            'name' => 'Любимое',
        ]);

        $this->getJson($this->url($user, $collection))
            ->assertOk()
            ->assertJsonPath('data.name', 'Любимое')
            ->assertJsonPath('data.visibility', CollectionVisibility::Personal->value)
            ->assertJsonPath('data.path', '/users/'.$user->public_key.'/collections/'.$collection->public_key);
    }

    public function test_show_returns_404_for_public_collection_on_nested_url(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->public()->create(['user_id' => $user->id]);

        // У публичной коллекции единственный адрес — короткий.
        $this->getJson($this->url($user, $collection))->assertNotFound();
    }

    public function test_show_returns_404_for_hidden_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->hidden()->create(['user_id' => $user->id]);

        $this->getJson($this->url($user, $collection))->assertNotFound();
    }

    public function test_show_returns_404_for_hidden_collection_even_for_owner(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->hidden()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson($this->url($user, $collection))
            ->assertNotFound();
    }

    public function test_show_returns_404_when_collection_belongs_to_another_user(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $collection = Collection::factory()->personal()->create(['user_id' => $owner->id]);

        $this->getJson($this->url($stranger, $collection))->assertNotFound();
    }

    public function test_show_returns_404_for_keys_without_leading_id(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);
        Collection::factory()->personal()->create(['user_id' => $user->id, 'name' => 'Любимое']);

        $this->getJson('/api/users/ivan/collections/lyubimoe')->assertNotFound();
        $this->getJson('/api/users/'.$user->public_key.'/collections/lyubimoe')->assertNotFound();
    }

    public function test_show_ignores_outdated_slugs_in_both_segments(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);
        $collection = Collection::factory()->personal()->create([
            'user_id' => $user->id,
            'name' => 'Любимое',
        ]);

        $this->getJson('/api/users/'.$user->id.'-staryj-nik/collections/'.$collection->id.'-staroe-imya')
            ->assertOk()
            ->assertJsonPath('data.name', 'Любимое');
    }

    public function test_show_returns_author_and_films_in_position_order(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);
        $collection = Collection::factory()->personal()->create(['user_id' => $user->id]);

        $second = Film::factory()->create(['name' => 'Второй']);
        $first = Film::factory()->create(['name' => 'Первый']);

        CollectionFilm::factory()->create([
            'collection_id' => $collection->id,
            'film_id' => $second->id,
            'position' => 2,
        ]);

        CollectionFilm::factory()->create([
            'collection_id' => $collection->id,
            'film_id' => $first->id,
            'position' => 1,
        ]);

        $this->getJson($this->url($user, $collection))
            ->assertOk()
            ->assertJsonPath('data.author.name', 'Иван')
            ->assertJsonPath('data.films_count', 2)
            ->assertJsonPath('data.films.0.film.name', 'Первый')
            ->assertJsonPath('data.films.1.film.name', 'Второй');
    }

    public function test_show_does_not_leak_owner_email(): void
    {
        $user = User::factory()->create(['email' => 'secret@example.com']);
        $collection = Collection::factory()->personal()->create(['user_id' => $user->id]);

        $this->getJson($this->url($user, $collection))
            ->assertOk()
            ->assertDontSee('secret@example.com');
    }
}
