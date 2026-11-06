<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MealSlot;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MealSlot> */
class MealSlotFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->randomElement(['Brunch', 'Snack', 'Dessert']),
        ];
    }
}
