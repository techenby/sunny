<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use App\Actions\Recipes\RemixRecipe as RemixRecipeAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Remix a recipe of the current team: create a copy named "<name> (Remix)" in the same team, linked to the original through parent_id, so it can be changed without touching the original. Use update-recipe afterwards to edit the remix.')]
class RemixRecipe extends Tool
{
    public function handle(Request $request, RemixRecipeAction $action): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $recipe = $request->user()->currentTeam->recipes()->whereKey($validated['id'])->first();

        if (! $recipe) {
            return Response::error('Recipe not found.');
        }

        Gate::forUser($request->user())->authorize('remix', $recipe);

        $remix = $action->handle($recipe);

        return Response::structured([
            'id' => $remix->id,
            'name' => $remix->name,
            'slug' => $remix->slug,
            'parent_id' => $remix->parent_id,
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the recipe to remix. Use the search-recipes tool to find ids.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->required(),
            'parent_id' => $schema->integer()->required()
                ->description('The id of the original recipe.'),
        ];
    }
}
