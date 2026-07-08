<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rules\Enum;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search the home inventory of the current team. Items form a hierarchy: locations contain bins, bins contain items. Filter by name, type, or parent to browse the tree. Returns a compact list; use the get-item tool for full details.')]
class SearchItems extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', new Enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'] ?? 20;

        $items = $request->user()->currentTeam->items()
            ->when(isset($validated['query']), fn ($query) => $query->where('name', 'like', '%' . $validated['query'] . '%'))
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->when(isset($validated['parent_id']), fn ($query) => $query->where('parent_id', $validated['parent_id']))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'type', 'parent_id']);

        if ($items->isEmpty()) {
            return Response::text('No items found matching the given filters.');
        }

        $lines = $items->map(fn (Item $item): string => sprintf(
            '#%d %s (type: %s, parent_id: %s)',
            $item->id,
            $item->name,
            $item->type->value,
            $item->parent_id ?? 'none',
        ));

        return Response::text(
            "Found {$items->count()} item(s):\n\n"
            . $lines->implode("\n")
            . "\n\nUse the get-item tool with an item's id for full details, its children, and its location path.",
        );
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Filter items whose name contains this text (partial match).'),
            'type' => $schema->string()
                ->enum(ItemType::class)
                ->description('Filter by item type: "location" (e.g. a room or shelf), "bin" (a container), or "item" (a thing).')
                ->nullable(),
            'parent_id' => $schema->integer()
                ->description('Only return direct children of the item with this id. Useful for browsing the hierarchy level by level.')
                ->nullable(),
            'limit' => $schema->integer()
                ->min(1)
                ->max(100)
                ->default(20)
                ->description('Maximum number of items to return (default 20, max 100).')
                ->nullable(),
        ];
    }
}
