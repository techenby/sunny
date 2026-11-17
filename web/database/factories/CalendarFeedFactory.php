<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<CalendarFeed>
 */
class CalendarFeedFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'url' => fake()->url() . '/calendar.ics',
            'color' => fake()->randomElement(CalendarColor::class),
        ];
    }

    public function failing(): static
    {
        return $this->state([
            'last_failed_at' => now(),
            'last_error' => 'The calendar server responded with HTTP 401.',
        ]);
    }

    public function withIcs(string $ics): static
    {
        return $this->state(['ics_fetched_at' => now()])
            ->afterCreating(fn (CalendarFeed $feed) => Storage::put($feed->icsPath(), $ics));
    }
}
