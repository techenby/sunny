<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Enums\ItemType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Restore a deleted inventory item of the current team by id. Use search-items with trashed: true to find the ids of deleted items. Children that were detached when the item was deleted stay top-level; use update-item to move them back.')]
class RestoreItem extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $item = $request->user()->currentTeam->items()->onlyTrashed()->find($validated['id']);

        if ($item === null) {
            return Response::error('Deleted item not found. Use search-items with trashed: true to find deleted items.');
        }

        Gate::forUser($request->user())->authorize('restore', $item);

        $item->restore();

        return Response::structured([
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type->value,
            'parent_id' => $item->parent_id,
            'updated_at' => $item->updated_at->toIso8601String(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the deleted inventory item to restore.')
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
            'parent_id' => $schema->integer()->nullable()->required(),
            'updated_at' => $schema->string()->required(),
        ];
    }
}
