<?php

namespace Database\Factories;

use App\Enums\FilmFormat;
use App\Enums\FilmModerationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilmFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => fake()->sentence(3),
            'format'            => FilmFormat::Film,
            'author_id'         => User::factory(),
            'moderation_status' => FilmModerationStatus::Draft,
        ];
    }
}
