<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MealPlanSlot;
use App\Models\MealSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MealPlanSlot> */
class MealPlanSlotFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'meal_slot_id' => MealSlot::factory(),
            'team_id' => fn (array $attributes): mixed => MealSlot::withTrashed()->whereKey($attributes['meal_slot_id'])->value('team_id'),
            'date' => now()->toDateString(),
        ];
    }
}
