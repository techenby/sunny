<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search the home inventory of the current team. Items form a hierarchy: locations contain bins, bins contain items. Filter by name, type, or parent to browse the tree. Returns a compact list; use the get-item tool for full details. Set trashed to true to search only deleted items instead, e.g. to find an id for the restore-item tool.')]
class SearchItems extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        Gate::forUser($request->user())->authorize('viewAny', Item::class);

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', new Enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'trashed' => ['nullable', 'boolean'],
        ]);

        $trashed = (bool) ($validated['trashed'] ?? false);

        $limit = $validated['limit'] ?? 20;

        $itemsQuery = Item::query()
            ->whereBelongsTo($request->user()->currentTeam)
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->when(isset($validated['parent_id']), fn ($query) => $query->where('parent_id', $validated['parent_id']))
            ->orderBy('name');

        $items = $this->search($itemsQuery, $validated['query'] ?? null, $limit);

        return Response::structured([
            'count' => $items->count(),
            'items' => $items->map(fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type->value,
                'parent_id' => $item->parent_id,
            ])->all(),
            'note' => match (true) {
                $items->isEmpty() => $trashed ? 'No deleted items found matching the given filters.' : 'No items found matching the given filters.',
                $trashed => "These items are deleted. Use the restore-item tool with an item's id to bring it back.",
                default => "Use the get-item tool with an item's id for full details, its children, and its location path.",
            },
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search item names. Multi-word searches try the full phrase first, then items matching any meaningful word, so related names can still be found.'),
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
            'trashed' => $schema->boolean()
                ->default(false)
                ->description('When true, search only deleted items instead of active ones. Use this to find ids for the restore-item tool.')
                ->nullable(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()->required(),
            'items' => $schema->array()->items($schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
                'type' => $schema->string()->enum(ItemType::class)->required(),
                'parent_id' => $schema->integer()->nullable()->required(),
            ]))->required(),
            'note' => $schema->string()->required(),
        ];
    }

    /**
     * @param  Builder<Item>  $itemsQuery
     * @return Collection<int, Item>
     */
    private function search(Builder $itemsQuery, ?string $query, int $limit): Collection
    {
        if ($query === null || trim($query) === '') {
            return $itemsQuery
                ->limit($limit)
                ->get(['id', 'name', 'type', 'parent_id']);
        }

        $query = Str::squish($query);

        $items = (clone $itemsQuery)
            ->whereLike('name', "%{$query}%")
            ->limit($limit)
            ->get(['id', 'name', 'type', 'parent_id']);

        $remaining = $limit - $items->count();
        $terms = $this->meaningfulTerms($query);

        if ($remaining === 0 || count($terms) < 2) {
            return $items;
        }

        $similarItems = (clone $itemsQuery)
            ->whereNotIn('id', $items->modelKeys())
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhereLike('name', "%{$term}%");
                }
            })
            ->limit($remaining)
            ->get(['id', 'name', 'type', 'parent_id']);

        return $items->concat($similarItems);
    }

    /** @return list<string> */
    private function meaningfulTerms(string $query): array
    {
        // Split on one or more non-letter/non-number characters with Unicode support, discarding empty terms.
        return Str::of($query)
            ->split('/[^\p{L}\p{N}]+/u', flags: PREG_SPLIT_NO_EMPTY)
            ->filter(fn (string $term): bool => Str::length($term) > 1)
            ->uniqueStrict()
            ->values()
            ->all();
    }
}
