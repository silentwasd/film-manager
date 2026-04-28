<?php

namespace Database\Factories;

use App\Models\Film;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedbackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'film_id'  => Film::factory(),
            'user_id'  => User::factory(),
            'reaction' => fake()->randomElement([-1, 1]),
            'text'     => null,
        ];
    }
}
