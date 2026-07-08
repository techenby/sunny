<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use App\Actions\Recipes\DeleteRecipe as DeleteRecipeAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a recipe from the current team by id.')]
class DeleteRecipe extends Tool
{
    public function handle(Request $request, DeleteRecipeAction $action): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $recipe = $request->user()->currentTeam->recipes()->whereKey($validated['id'])->first();

        if (! $recipe) {
            return Response::error('Recipe not found.');
        }

        Gate::forUser($request->user())->authorize('delete', $recipe);

        $action->handle($recipe);

        return Response::text("The recipe \"{$recipe->name}\" has been deleted.");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the recipe to delete.')
                ->required(),
        ];
    }
}
