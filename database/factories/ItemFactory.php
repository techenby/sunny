<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Container;
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
        ];
    }

    public function inContainer(Container $container): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $container->team_id,
            'container_id' => $container->id,
        ]);
    }
}
