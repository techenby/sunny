<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use App\Actions\Recipes\UpdateRecipe as UpdateRecipeAction;
use App\Rules\RecipeLines;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update an existing recipe on the current team. Only the provided fields are changed. The ingredients and instructions fields take plain text with one entry per line. Start a line with "# " to make it a section heading.')]
class UpdateRecipe extends Tool
{
    public function handle(Request $request, UpdateRecipeAction $action): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'ingredients' => ['nullable', 'string', 'max:' . RecipeLines::MAX_CHARACTERS],
            'instructions' => ['nullable', 'string', 'max:' . RecipeLines::MAX_CHARACTERS],
            'servings' => ['nullable', 'string', 'max:50'],
            'prep_time' => ['nullable', 'string', 'max:50'],
            'cook_time' => ['nullable', 'string', 'max:50'],
            'total_time' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'source' => ['nullable', 'string', 'max:500'],
            'nutrition' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:255'],
        ]);

        $recipe = $request->user()->currentTeam->recipes()->whereKey($validated['id'])->first();

        if (! $recipe) {
            return Response::error('Recipe not found.');
        }

        Gate::forUser($request->user())->authorize('update', $recipe);

        $recipe = $action->handle(
            $recipe,
            Arr::except($validated, ['id']),
        );

        return Response::text(json_encode($recipe->only([
            'id', 'name', 'slug', 'description', 'ingredients', 'instructions',
            'servings', 'prep_time', 'cook_time', 'total_time', 'notes', 'source',
            'nutrition', 'tags',
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the recipe to update.')
                ->required(),
            'name' => $schema->string()
                ->description('The new name of the recipe.'),
            'description' => $schema->string()
                ->description('A short description of the recipe.'),
            'ingredients' => $schema->string()
                ->description('The ingredients, as plain text with one ingredient per line. Start a line with "# " for a section heading.'),
            'instructions' => $schema->string()
                ->description('The instructions, as plain text with one step per line. Start a line with "# " for a section heading.'),
            'servings' => $schema->string()
                ->description('How many servings the recipe yields, e.g. "4" or "8 servings".'),
            'prep_time' => $schema->string()
                ->description('The preparation time, e.g. "15m" or "20 minutes".'),
            'cook_time' => $schema->string()
                ->description('The cooking time, e.g. "45m" or "1 hour".'),
            'total_time' => $schema->string()
                ->description('The total time, e.g. "1h" or "1 hour 15 minutes".'),
            'notes' => $schema->string()
                ->description('Freeform notes about the recipe.'),
            'source' => $schema->string()
                ->description('Where the recipe came from: a URL, book title, or person.'),
            'nutrition' => $schema->string()
                ->description('Nutrition information as plain text with one fact per line, e.g. "Calories: 320" then "Protein: 12g".'),
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Tags to categorize the recipe, e.g. ["Dinner", "Vegetarian"]. Replaces the existing tags.'),
        ];
    }
}
