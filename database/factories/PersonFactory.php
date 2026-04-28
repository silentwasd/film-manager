<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => fake()->name(),
            'author_id' => User::factory(),
        ];
    }
}
