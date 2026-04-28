<?php

namespace Database\Factories;

use App\Enums\PersonRole;
use App\Models\Film;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilmPersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'film_id'   => Film::factory(),
            'person_id' => Person::factory(),
            'role'      => PersonRole::Actor,
            'order_id'  => 1,
        ];
    }
}
