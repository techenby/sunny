<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\Routine;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Routine> */
class RoutineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => null,
            'name' => fake()->words(2, true),
            'time_of_day' => TimeOfDay::Morning,
            'frequency' => RoutineFrequency::Daily,
            'starts_on' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => RoutineFrequency::Daily,
            'weekdays' => null,
            'day_of_month' => null,
        ]);
    }

    public function household(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function monthly(int $dayOfMonth = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => RoutineFrequency::Monthly,
            'weekdays' => null,
            'day_of_month' => $dayOfMonth,
        ]);
    }

    public function timeOfDay(TimeOfDay $timeOfDay): static
    {
        return $this->state(fn (array $attributes) => [
            'time_of_day' => $timeOfDay,
        ]);
    }

    /** @param array<int, int> $weekdays */
    public function weekly(array $weekdays = [Carbon::MONDAY]): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => RoutineFrequency::Weekly,
            'weekdays' => $weekdays,
            'day_of_month' => null,
        ]);
    }
}
