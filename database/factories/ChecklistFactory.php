<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Checklist> */
class ChecklistFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => null,
            'name' => fake()->words(2, true),
            'type' => ChecklistType::Todo,
        ];
    }

    public function household(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function shopping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChecklistType::Shopping,
        ]);
    }

    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChecklistType::Todo,
        ]);
    }

    public function wishlist(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChecklistType::Wishlist,
        ]);
    }
}
