<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get full details for a single inventory item: its fields and metadata, its direct children, and its location path (the chain of parents, e.g. "Garage > Shelf 3 > Blue Bin"), which answers "where is this item?".')]
class GetItem extends Tool
{
    public function handle(Request $request): Response
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

        $lines = [
            "# {$item->name}",
            "ID: {$item->id}",
            "Type: {$item->type->value}",
            'Location: ' . ($this->breadcrumb($item) ?? 'Top level (no parent)'),
            'Parent ID: ' . ($item->parent_id ?? 'none'),
            'Metadata: ' . ($item->metadata ? json_encode($item->metadata) : 'none'),
            "Created: {$item->created_at->toDateTimeString()}",
            "Updated: {$item->updated_at->toDateTimeString()}",
            '',
        ];

        if ($item->children->isEmpty()) {
            $lines[] = 'Children: none';
        } else {
            $lines[] = "Children ({$item->children->count()}):";

            foreach ($item->children as $child) {
                $lines[] = "- #{$child->id} {$child->name} ({$child->type->value})";
            }
        }

        return Response::text(implode("\n", $lines));
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
