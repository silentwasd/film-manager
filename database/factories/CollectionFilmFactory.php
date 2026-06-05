<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\CollectionFilm;
use App\Models\Film;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionFilm>
 */
class CollectionFilmFactory extends Factory
{
    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'film_id' => Film::factory(),
            'position' => $this->faker->numberBetween(1, 100),
            'note' => null,
        ];
    }
}
