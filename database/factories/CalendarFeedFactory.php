<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalendarFeed;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarFeed>
 */
class CalendarFeedFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'url' => fake()->url() . '/calendar.ics',
            'color' => fake()->randomElement(['#2563eb', '#16a34a', '#dc2626', '#9333ea', '#ea580c', '#0891b2']),
        ];
    }
}
