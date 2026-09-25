<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Routine;
use App\Models\RoutineOccurrence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoutineOccurrence> */
class RoutineOccurrenceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'routine_id' => Routine::factory(),
            'due_on' => now()->toDateString(),
            'generated_at' => now(),
        ];
    }

    public function dueOn(CarbonInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'due_on' => $date->toDateString(),
        ]);
    }
}
