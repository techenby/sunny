<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Actions\Inventory\MoveItemToTeam as MoveItemToTeamAction;
use App\Enums\ItemType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Move an inventory item from the current team to another team the user belongs to. The item becomes a top-level item in the target team; its children stay in the current team. After the move the item is no longer visible through this server until the user switches teams.')]
class MoveItemToTeam extends Tool
{
    public function handle(Request $request, MoveItemToTeamAction $action): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'team_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        $item = $user->currentTeam->items()->find($validated['id']);

        if ($item === null) {
            return Response::error('Item not found.');
        }

        Gate::forUser($user)->authorize('move', $item);

        $team = $user->teams
            ->where('id', '!=', $user->current_team_id)
            ->firstWhere('id', $validated['team_id']);

        if ($team === null) {
            return Response::error('Team not found. The team_id must be another team you belong to, not the current team.');
        }

        $item = $action->handle($item, $team);

        return Response::structured([
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type->value,
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
                ->description('The id of the inventory item to move. Use the search-items tool to find ids.')
                ->required(),
            'team_id' => $schema->integer()
                ->description('The id of the team to move the item to. Must be another team you belong to.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'type' => $schema->string()->enum(ItemType::class)->required(),
            'team' => $schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
            ])->required(),
        ];
    }
}
