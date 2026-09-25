<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use App\Actions\Recipes\CreateRecipe as CreateRecipeAction;
use App\Mcp\Tools\Recipes\Concerns\NormalizesRecipeLists;
use App\Models\Recipe;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new recipe for the current team. The ingredients and instructions fields accept either plain text (one item per line) or HTML lists; plain text is automatically wrapped in <ul>/<ol> lists.')]
class CreateRecipe extends Tool
{
    use NormalizesRecipeLists;

    public function handle(Request $request, CreateRecipeAction $action): Response
    {
        Gate::forUser($request->user())->authorize('create', Recipe::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ingredients' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'servings' => ['nullable', 'string', 'max:50'],
            'prep_time' => ['nullable', 'string', 'max:50'],
            'cook_time' => ['nullable', 'string', 'max:50'],
            'total_time' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ]);

        $recipe = $action->handle(
            $request->user()->currentTeam,
            $this->normalizeRecipeLists($validated),
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
                ->description('The ingredients, as plain text with one ingredient per line or as an HTML <ul> list.'),
            'instructions' => $schema->string()
                ->description('The instructions, as plain text with one step per line or as an HTML <ol> list.'),
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
