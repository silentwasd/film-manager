<?php

namespace Database\Factories;

use App\Enums\FilmWatchStatus;
use App\Models\Film;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilmWatcherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'film_id'    => Film::factory(),
            'watcher_id' => User::factory(),
            'status'     => FilmWatchStatus::ToWatch,
        ];
    }
}
