<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use App\Actions\Recipes\CopyRecipeToTeam as CopyRecipeToTeamAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Copy a recipe from the current team to another team the user belongs to. The original stays in the current team; the copy is not shared publicly. The copy lives in the other team, so it is not visible through this server until the user switches teams.')]
class CopyRecipeToTeam extends Tool
{
    public function handle(Request $request, CopyRecipeToTeamAction $action): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'team_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        $recipe = $user->currentTeam->recipes()->whereKey($validated['id'])->first();

        if (! $recipe) {
            return Response::error('Recipe not found.');
        }

        Gate::forUser($user)->authorize('copy', $recipe);

        $team = $user->teams
            ->where('id', '!=', $user->current_team_id)
            ->firstWhere('id', $validated['team_id']);

        if ($team === null) {
            return Response::error('Team not found. The team_id must be another team you belong to, not the current team.');
        }

        $copy = $action->handle($recipe, $team);

        return Response::structured([
            'id' => $copy->id,
            'name' => $copy->name,
            'slug' => $copy->slug,
            'parent_id' => $copy->parent_id,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the recipe to copy. Use the search-recipes tool to find ids.')
                ->required(),
            'team_id' => $schema->integer()
                ->description('The id of the team to copy the recipe to. Must be another team you belong to.')
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
            'team' => $schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
            ])->required(),
        ];
    }
}
