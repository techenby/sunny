<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Recipe> */
class RecipeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->sentence(3),
            'slug' => fn (array $attributes) => Str::slug($attributes['name']),
        ];
    }

    public function withTags(array $tags = []): static
    {
        return $this->afterCreating(function (Recipe $recipe) use ($tags) {
            $tagsToAttach = $tags ?: fake()->randomElements(
                ['Breakfast', 'Lunch', 'Dinner', 'Dessert', 'Vegetarian', 'Vegan', 'Gluten-Free'],
                fake()->numberBetween(1, 3)
            );

            $recipe->attachTags($tagsToAttach);
        });
    }

    public function remixOf(Recipe $parent): static
    {
        return $this->state(fn () => [
            'team_id' => $parent->team_id,
            'parent_id' => $parent->id,
            'name' => $parent->name . ' (Remix)',
        ]);
    }
}
