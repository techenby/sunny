<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Appearance;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'is_personal' => false,
            'timezone' => 'America/Chicago',
            'week_start' => Carbon::SUNDAY,
            'appearance' => Appearance::Dark,
            'rotation' => 0,
            'screensaver_after' => 5,
            'return_home_after' => 10,
        ];
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_personal' => true,
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
