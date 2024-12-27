<?php

namespace Database\Factories;

use App\Models\LegoGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LegoPiece>
 */
class LegoPieceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'group_id' => LegoGroup::factory(),
            'name' => 'DUPLO 2×2 Brick',
            'part_number' => rand(1, 1000),
            'image' => 'https://brickarchitect.com/content/parts/3437.png',
            'href' => 'https://brickarchitect.com/parts/3437',
        ];
    }
}
