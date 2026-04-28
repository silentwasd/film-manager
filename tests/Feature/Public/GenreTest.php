<?php

namespace Tests\Feature\Public;

use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_genre_by_slug(): void
    {
        $genre = Genre::factory()->create(['name' => 'Action', 'slug' => 'action']);

        $this->getJson('/api/genre/action')
             ->assertOk()
             ->assertJsonPath('data.name', 'Action');
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/genre/nonexistent')->assertNotFound();
    }
}
