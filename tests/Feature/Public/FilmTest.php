<?php

namespace Tests\Feature\Public;

use App\Models\Film;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmTest extends TestCase
{
    use RefreshDatabase;

    private function visibleFilm(User $user, array $overrides = []): Film
    {
        return Film::factory()->create(array_merge([
            'author_id'     => $user->id,
            'cover'         => 'films/cover.jpg',
            'produced_year' => now()->year - 1,
        ], $overrides));
    }

    public function test_index_returns_films_with_cover_and_past_year(): void
    {
        $user = User::factory()->create();
        $this->visibleFilm($user);

        $this->getJson('/api/films')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_excludes_films_without_cover(): void
    {
        $user = User::factory()->create();
        Film::factory()->create(['author_id' => $user->id, 'produced_year' => now()->year - 1]);

        $this->getJson('/api/films')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_excludes_films_without_produced_year(): void
    {
        $user = User::factory()->create();
        Film::factory()->create(['author_id' => $user->id, 'cover' => 'films/cover.jpg']);

        $this->getJson('/api/films')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_excludes_future_films(): void
    {
        $user = User::factory()->create();
        $this->visibleFilm($user, ['produced_year' => now()->year + 1]);

        $this->getJson('/api/films')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_filters_by_name(): void
    {
        $user = User::factory()->create();
        $this->visibleFilm($user, ['name' => 'The Matrix']);
        $this->visibleFilm($user, ['name' => 'Inception']);

        $response = $this->getJson('/api/films?name=Matrix');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('The Matrix', $response->json('data.0.name'));
    }

    public function test_index_filters_by_original_name(): void
    {
        $user = User::factory()->create();
        $this->visibleFilm($user, ['name' => 'Матрица', 'original_name' => 'The Matrix']);
        $this->visibleFilm($user, ['name' => 'Начало']);

        $response = $this->getJson('/api/films?name=Matrix');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_is_paginated(): void
    {
        $user = User::factory()->create();

        Film::factory()->count(3)->create([
            'author_id'     => $user->id,
            'cover'         => 'films/cover.jpg',
            'produced_year' => now()->year - 1,
        ]);

        $this->getJson('/api/films')->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
    }
}
