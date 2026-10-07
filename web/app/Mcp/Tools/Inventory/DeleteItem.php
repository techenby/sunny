<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Move an inventory item of the current team to the trash by id. Its direct children are not deleted; they become top-level items. A deleted item can be found with search-items (trashed: true) and brought back with restore-item.')]
class DeleteItem extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $item = $request->user()->currentTeam->items()->find($validated['id']);

        if ($item === null) {
            return Response::error('Item not found.');
        }

        Gate::forUser($request->user())->authorize('delete', $item);

        $item->purge();

        return Response::text("The item \"{$item->name}\" (ID {$item->id}) has been deleted. Its children are now top-level items. It can be restored with the restore-item tool.");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the inventory item to delete. Use the search-items tool to find ids.')
                ->required(),
        ];
    }
}
