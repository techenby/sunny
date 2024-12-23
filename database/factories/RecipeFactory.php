<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Mashed Potatoes',
        ];
    }

    public function withImage()
    {
        return $this->afterCreating(function (Recipe $recipe) {
            $image = UploadedFile::fake()->image('image.jpg');
            $recipe->addMedia($image)->toMediaCollection('thumb');
        });
    }
}
