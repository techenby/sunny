<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContainerType;
use App\Models\Container;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Container> */
class ContainerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'type' => fake()->randomElement(ContainerType::cases()),
            'name' => fake()->word(),
        ];
    }

    public function location(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ContainerType::Location,
        ]);
    }

    public function bin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ContainerType::Bin,
        ]);
    }

    public function childOf(Container $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $parent->team_id,
            'parent_id' => $parent->id,
        ]);
    }
}
