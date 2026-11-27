<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Actions\Inventory\RestoreItem as RestoreItemAction;
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
#[Description('Restore a deleted inventory item of the current team by id. Use search-items with trashed: true to find the ids of deleted items. Children that were detached when the item was deleted stay top-level; use update-item to move them back. If the parent of the item is still deleted, the item comes back top-level. Restoring an item that is not deleted returns it unchanged, so a retry is safe.')]
class RestoreItem extends Tool
{
    public function handle(Request $request, RestoreItemAction $action): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $item = $request->user()->currentTeam->items()->withTrashed()->find($validated['id']);

        if ($item === null) {
            return Response::error('Item not found. Use search-items with trashed: true to find deleted items.');
        }

        Gate::forUser($request->user())->authorize('restore', $item);

        $action->handle($item);

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
