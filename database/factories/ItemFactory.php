<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContainerType;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->word(),
            'type' => fake()->randomElement(ItemType::class),
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

    public function container(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => fake()->randomElement(ContainerType::cases()),
        ]);
    }

    public function childOf(Item $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $parent->team_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function inContainer(Item $container): static
    {
        return $this->childOf($container);
    }
}
