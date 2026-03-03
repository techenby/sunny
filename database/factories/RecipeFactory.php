<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
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
            'source' => fake()->optional()->url(),
            'servings' => fake()->optional()->numberBetween(1, 12) . ' ' . fake()->optional()->randomElement(['people', 'servings', 'pieces']),
            'prep_time' => fake()->optional()->randomElement(['15 min', '30 min', '1 hour', '2 hours']),
            'cook_time' => fake()->optional()->randomElement(['15 min', '30 min', '1 hour', '2 hours']),
            'total_time' => fake()->optional()->randomElement(['30 min', '1 hour', '2 hours', '3 hours']),
            'description' => fake()->optional()->paragraph(),
            'ingredients' => fake()->optional()->text(),
            'instructions' => fake()->optional()->text(),
            'notes' => fake()->optional()->text(),
            'nutrition' => fake()->optional()->text(),
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
