<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LegoGroup>
 */
class LegoGroupFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'DUPLO',
            'href' => 'https://brickarchitect.com/parts/category-89',
            'summary' => 'The larger bricks for younger kids are twice as big in each direction and compatible with regular LEGO bricks',
        ];
    }
}
