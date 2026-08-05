<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Routine;
use App\Models\RoutineTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineTask>
 */
class RoutineTaskFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'routine_id' => Routine::factory(),
            'name' => fake()->sentence(3),
            'order' => fake()->numberBetween(0, 10),
        ];
    }
}
