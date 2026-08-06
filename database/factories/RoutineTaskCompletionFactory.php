<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RoutineTask;
use App\Models\RoutineTaskCompletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineTaskCompletion>
 */
class RoutineTaskCompletionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'task_id' => RoutineTask::factory(),
            'user_id' => User::factory(),
            'period_started_on' => now()->toDateString(),
            'completed_at' => now(),
        ];
    }
}
