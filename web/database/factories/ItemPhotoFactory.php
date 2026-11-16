<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemPhoto> */
class ItemPhotoFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'path' => 'teams/1/items/' . fake()->unique()->slug(2) . '.jpg',
            'position' => 0,
        ];
    }
}
