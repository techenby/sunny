<?php

namespace Database\Factories;

use App\Models\Crew;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrewInvitation> */
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
