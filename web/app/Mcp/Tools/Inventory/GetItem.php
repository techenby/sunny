<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get full details for a single inventory item: its fields and metadata, its direct children, and its location path (the chain of parents, e.g. "Garage > Shelf 3 > Blue Bin"), which answers "where is this item?".')]
class GetItem extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $item = $request->user()->currentTeam->items()
            ->with('children')
            ->find($validated['id']);

        if ($item === null) {
            return Response::error('Item not found.');
        }

        return Response::structured([
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type->value,
            'location_path' => $this->breadcrumb($item),
            'parent_id' => $item->parent_id,
            'metadata' => $item->metadata ?: null,
            'children' => $item->children->map(fn (Item $child): array => [
                'id' => $child->id,
                'name' => $child->name,
                'type' => $child->type->value,
            ])->all(),
            'created_at' => $item->created_at->toIso8601String(),
            'updated_at' => $item->updated_at->toIso8601String(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the inventory item to retrieve. Use the search-items tool to find ids.')
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
            'location_path' => $schema->string()->nullable()->required()
                ->description('The chain of parent names, e.g. "Garage > Shelf 3 > Blue Bin". Null for top-level items.'),
            'parent_id' => $schema->integer()->nullable()->required(),
            'metadata' => $schema->object()->nullable()->required(),
            'children' => $schema->array()->items($schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
                'type' => $schema->string()->enum(ItemType::class)->required(),
            ]))->required()
                ->description('Direct children only.'),
            'created_at' => $schema->string()->required(),
            'updated_at' => $schema->string()->required(),
        ];
    }

    protected function breadcrumb(Item $item): ?string
    {
        $names = collect();

        $ancestor = $item->parent;

        while ($ancestor !== null) {
            $names->prepend($ancestor->name);

            $ancestor = $ancestor->parent;
        }

        return $names->isEmpty() ? null : $names->implode(' > ');
    }
}
