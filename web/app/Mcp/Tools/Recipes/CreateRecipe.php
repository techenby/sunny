<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use App\Actions\Recipes\CreateRecipe as CreateRecipeAction;
use App\Models\Recipe;
use App\Rules\RecipeLines;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new recipe for the current team. The ingredients and instructions fields take plain text with one entry per line. Start a line with "# " to make it a section heading.')]
class CreateRecipe extends Tool
{
    public function handle(Request $request, CreateRecipeAction $action): Response
    {
        Gate::forUser($request->user())->authorize('create', Recipe::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'ingredients' => ['nullable', 'string', 'max:' . RecipeLines::MAX_CHARACTERS],
            'instructions' => ['nullable', 'string', 'max:' . RecipeLines::MAX_CHARACTERS],
            'servings' => ['nullable', 'string', 'max:50'],
            'prep_time' => ['nullable', 'string', 'max:50'],
            'cook_time' => ['nullable', 'string', 'max:50'],
            'total_time' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'source' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:255'],
        ]);

        $recipe = $action->handle(
            $request->user()->currentTeam,
            $validated,
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
            'name' => $schema->string()
                ->description('The name of the recipe.')
                ->required(),
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
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Tags to categorize the recipe, e.g. ["Dinner", "Vegetarian"].'),
        ];
    }
}
