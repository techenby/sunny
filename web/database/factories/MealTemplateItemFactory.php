<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MealSlot;
use App\Models\MealTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MealTemplateItem> */
class MealTemplateItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'meal_slot_id' => MealSlot::factory(),
            'team_id' => fn (array $attributes): mixed => MealSlot::withTrashed()->whereKey($attributes['meal_slot_id'])->value('team_id'),
            'weekday' => fake()->numberBetween(0, 6),
            'name' => fake()->words(2, true),
        ];
    }
}
