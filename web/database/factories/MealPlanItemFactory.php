<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MealPlanItem;
use App\Models\MealPlanSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MealPlanItem> */
class MealPlanItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'meal_plan_slot_id' => MealPlanSlot::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
