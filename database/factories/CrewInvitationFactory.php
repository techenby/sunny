<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CrewInvitation;
use App\Models\Crew;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewInvitation> */
class CrewInvitationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'crew_id' => Crew::factory(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
