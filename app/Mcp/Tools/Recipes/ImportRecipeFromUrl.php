<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use App\Actions\Recipes\CreateRecipe as CreateRecipeAction;
use App\Actions\Recipes\ImportRecipeFromUrl as ImportRecipeFromUrlAction;
use App\Models\Recipe;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;

#[IsOpenWorld]
#[Description("Import a recipe from a web page URL and save it to the current team. Fetches the page, extracts the recipe's structured data (name, ingredients, instructions, times, nutrition, tags), and creates the recipe. Fails if the page has no recipe data.")]
class ImportRecipeFromUrl extends Tool
{
    public function handle(Request $request, ImportRecipeFromUrlAction $import, CreateRecipeAction $create): Response
    {
        Gate::forUser($request->user())->authorize('create', Recipe::class);

        $validated = $request->validate([
            'url' => ['required', 'url'],
        ], [
            'url.url' => 'The url must be a valid URL, including the scheme (e.g. https://example.com/recipe).',
        ]);

        try {
            $data = $import->handle($validated['url']);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (blank($data['name'] ?? null)) {
            return Response::error('No recipe name found on this page.');
        }

        $recipe = $create->handle($request->user()->currentTeam, $data);

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
            'url' => $schema->string()
                ->description('The full URL of the recipe web page to import, e.g. https://example.com/chocolate-cake.')
                ->required(),
        ];
    }
}
