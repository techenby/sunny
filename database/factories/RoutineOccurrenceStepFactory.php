<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoutineOccurrenceStep> */
class RoutineOccurrenceStepFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'routine_occurrence_id' => RoutineOccurrence::factory(),
            'routine_step_id' => RoutineStep::factory(),
        ];
    }

    public function completed(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'completed_by' => $user?->id,
        ]);
    }
}
