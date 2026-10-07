<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Turn public sharing of a current-team recipe on or off. When shared, anyone with the returned share_url can view the recipe without signing in. Turning sharing off invalidates the link; turning it on again creates a new link.')]
class UpdateRecipeSharing extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'shared' => ['required', 'boolean'],
        ], [
            'shared.required' => 'Set shared to true to enable public sharing or false to disable it.',
        ]);

        $recipe = $request->user()->currentTeam->recipes()->whereKey($validated['id'])->first();

        if (! $recipe) {
            return Response::error('Recipe not found.');
        }

        Gate::forUser($request->user())->authorize('share', $recipe);

        if ($validated['shared'] && ! $recipe->isShared()) {
            $recipe->enableSharing();
        } elseif (! $validated['shared'] && $recipe->isShared()) {
            $recipe->disableSharing();
        }

        return Response::structured([
            'id' => $recipe->id,
            'name' => $recipe->name,
            'shared' => $recipe->isShared(),
            'share_url' => $recipe->isShared() ? route('recipes.shared', $recipe->share_token) : null,
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the recipe. Use the search-recipes tool to find ids.')
                ->required(),
            'shared' => $schema->boolean()
                ->description('True to enable public sharing, false to disable it.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'shared' => $schema->boolean()->required(),
            'share_url' => $schema->string()->nullable()->required()
                ->description('The public link to the recipe. Null when sharing is off.'),
        ];
    }
}
