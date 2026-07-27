<?php

namespace Tests\Feature\Public;

use App\Models\Collection;
use App\Models\CollectionFilm;
use App\Models\Film;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_public_collection_without_authentication(): void
    {
        $collection = Collection::factory()->public()->create(['name' => 'Лучшие боевики']);

        $this->getJson('/api/collections/'.$collection->id.'-luchshie-boeviki')
            ->assertOk()
            ->assertJsonPath('data.id', $collection->id)
            ->assertJsonPath('data.name', 'Лучшие боевики');
    }

    public function test_show_returns_404_for_private_collection(): void
    {
        $collection = Collection::factory()->create(['is_public' => false]);

        $this->getJson('/api/collections/'.$collection->public_key)
            ->assertNotFound();
    }

    public function test_show_returns_404_for_owner_of_private_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['user_id' => $user->id, 'is_public' => false]);

        $this->actingAs($user)
            ->getJson('/api/collections/'.$collection->public_key)
            ->assertNotFound();
    }

    public function test_show_returns_404_for_key_without_leading_id(): void
    {
        Collection::factory()->public()->create();

        $this->getJson('/api/collections/luchshie-boeviki')->assertNotFound();
    }

    public function test_show_ignores_outdated_slug(): void
    {
        $collection = Collection::factory()->public()->create(['name' => 'Новое имя']);

        $this->getJson('/api/collections/'.$collection->id.'-sovsem-drugoj-slag')
            ->assertOk()
            ->assertJsonPath('data.name', 'Новое имя');
    }

    public function test_show_returns_author_and_films_in_position_order(): void
    {
        $user = User::factory()->create(['name' => 'Иван']);
        $collection = Collection::factory()->public()->create(['user_id' => $user->id]);

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

        $this->getJson('/api/collections/'.$collection->public_key)
            ->assertOk()
            ->assertJsonPath('data.author', 'Иван')
            ->assertJsonPath('data.films_count', 2)
            ->assertJsonPath('data.films.0.film.name', 'Первый')
            ->assertJsonPath('data.films.1.film.name', 'Второй');
    }

    public function test_show_does_not_leak_owner_email(): void
    {
        $user = User::factory()->create(['email' => 'secret@example.com']);
        $collection = Collection::factory()->public()->create(['user_id' => $user->id]);

        $this->getJson('/api/collections/'.$collection->public_key)
            ->assertOk()
            ->assertDontSee('secret@example.com');
    }

    public function test_public_key_falls_back_to_id_when_name_is_not_transliterable(): void
    {
        $collection = Collection::factory()->public()->create(['name' => '中文']);

        $this->assertSame((string) $collection->id, $collection->public_key);

        $this->getJson('/api/collections/'.$collection->public_key)->assertOk();
    }
}
