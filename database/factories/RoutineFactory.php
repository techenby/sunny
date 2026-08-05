<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoutineCadence;
use App\Models\Routine;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Routine>
 */
class RoutineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'cadence' => RoutineCadence::Daily,
            'reset_weekday' => null,
        ];
    }

    public function weekly(int $resetWeekday = 0): static
    {
        return $this->state([
            'cadence' => RoutineCadence::Weekly,
            'reset_weekday' => $resetWeekday,
        ]);
    }
}
