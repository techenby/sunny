<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Routine;
use App\Models\RoutineStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoutineStep> */
class RoutineStepFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'routine_id' => Routine::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
