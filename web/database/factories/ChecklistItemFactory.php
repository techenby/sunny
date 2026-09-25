<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChecklistItem> */
class ChecklistItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'name' => fake()->words(2, true),
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
