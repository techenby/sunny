<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Actions\Inventory\CreateItem as CreateItemAction;
use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new inventory item for the current team. Items form a hierarchy: pass a parent_id to place the new item inside an existing location or bin (e.g. a bin inside a shelf, an item inside a bin).')]
class CreateItem extends Tool
{
    public function handle(Request $request, CreateItemAction $action): Response
    {
        Gate::authorize('create', Item::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ]);

        $team = $request->user()->currentTeam;

        if (isset($validated['parent_id']) && $team->items()->whereKey($validated['parent_id'])->doesntExist()) {
            return Response::error('Parent item not found.');
        }

        $item = $action->handle($team, $validated);

        return Response::text(sprintf(
            'Created item #%d: %s (type: %s, parent_id: %s).',
            $item->id,
            $item->name,
            $item->type->value,
            $item->parent_id ?? 'none',
        ));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->max(255)
                ->description('The name of the new item.')
                ->required(),
            'type' => $schema->string()
                ->enum(ItemType::class)
                ->description('The item type: "location" (e.g. a room or shelf), "bin" (a container), or "item" (a thing).')
                ->required(),
            'parent_id' => $schema->integer()
                ->description('The id of an existing item (usually a location or bin) to place this item inside. Omit for a top-level item.'),
            'metadata' => $schema->object()
                ->description('Optional key/value metadata to store with the item (e.g. {"brand": "DeWalt", "serial": "123"}).'),
        ];
    }
}
