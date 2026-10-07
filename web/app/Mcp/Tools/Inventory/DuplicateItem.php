<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inventory;

use App\Actions\Inventory\DuplicateItem as DuplicateItemAction;
use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Duplicate an inventory item of the current team one or more times (1 to 25 copies). Each copy keeps the name, type, parent and metadata of the original, and gets its own copy of the photo. Children are not copied. Returns the new items.')]
class DuplicateItem extends Tool
{
    public function handle(Request $request, DuplicateItemAction $action): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'count' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ], [
            'count.min' => 'The count must be between 1 and 25.',
            'count.max' => 'The count must be between 1 and 25.',
        ]);

        $item = $request->user()->currentTeam->items()->find($validated['id']);

        if ($item === null) {
            return Response::error('Item not found.');
        }

        Gate::forUser($request->user())->authorize('view', $item);
        Gate::forUser($request->user())->authorize('create', Item::class);

        $copies = $action->handle($item, $validated['count'] ?? 1);

        return Response::structured([
            'count' => $copies->count(),
            'items' => $copies->map(fn (Item $copy): array => [
                'id' => $copy->id,
                'name' => $copy->name,
                'type' => $copy->type->value,
                'parent_id' => $copy->parent_id,
            ])->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the inventory item to duplicate. Use the search-items tool to find ids.')
                ->required(),
            'count' => $schema->integer()
                ->min(1)
                ->max(25)
                ->default(1)
                ->description('How many copies to create (default 1, max 25).'),
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
        ];
    }
}
