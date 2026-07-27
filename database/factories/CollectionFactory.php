<?php

namespace Database\Factories;

use App\Enums\CollectionVisibility;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'description' => null,
            'visibility' => CollectionVisibility::Hidden,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['visibility' => CollectionVisibility::Public]);
    }

    public function personal(): static
    {
        return $this->state(fn () => ['visibility' => CollectionVisibility::Personal]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['visibility' => CollectionVisibility::Hidden]);
    }
}
