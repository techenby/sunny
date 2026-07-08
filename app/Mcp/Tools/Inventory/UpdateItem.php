<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Actions\Inventory\UpdateItem as UpdateItemAction;
use App\Enums\ItemType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update an existing inventory item. Only the provided fields are changed. Move an item by passing a new parent_id (or null to make it top-level); an item cannot be moved inside itself or one of its descendants.')]
class UpdateItem extends Tool
{
    public function handle(Request $request, UpdateItemAction $action): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', new Enum(ItemType::class)],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $team = $request->user()->currentTeam;

        $item = $team->items()->find($validated['id']);

        if ($item === null) {
            return Response::error('Item not found.');
        }

        Gate::forUser($request->user())->authorize('update', $item);

        $data = Arr::except($validated, 'id');

        if (isset($data['parent_id'])) {
            if ($team->items()->whereKey($data['parent_id'])->doesntExist()) {
                return Response::error('Parent item not found.');
            }

            if ($data['parent_id'] === $item->id || $item->descendantIds()->contains($data['parent_id'])) {
                return Response::error('An item cannot be moved inside itself or one of its descendants.');
            }
        }

        $item = $action->handle($item, $data);

        return Response::text(sprintf(
            'Updated item #%d: %s (type: %s, parent_id: %s).',
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
            'id' => $schema->integer()
                ->description('The id of the inventory item to update. Use the search-items tool to find ids.')
                ->required(),
            'name' => $schema->string()
                ->max(255)
                ->description('A new name for the item.'),
            'type' => $schema->string()
                ->enum(ItemType::class)
                ->description('A new type for the item: "location", "bin", or "item".'),
            'parent_id' => $schema->integer()
                ->nullable()
                ->description('Move the item inside the item with this id, or pass null to make it top-level.'),
            'metadata' => $schema->object()
                ->nullable()
                ->description('Replace the item\'s key/value metadata (e.g. {"brand": "DeWalt"}). Pass null to clear it.'),
        ];
    }
}
