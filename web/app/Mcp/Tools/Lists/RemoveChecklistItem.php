<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Lists;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Permanently remove a single item from a checklist on the current team. To check an item off instead, use the update-checklist-item tool.')]
class RemoveChecklistItem extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'checklist_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
        ]);

        $checklist = $request->user()->currentTeam->checklists()->find($validated['checklist_id']);

        if ($checklist === null) {
            return Response::error('Checklist not found.');
        }

        $item = $checklist->items()->find($validated['item_id']);

        if ($item === null) {
            return Response::error('Checklist item not found.');
        }

        Gate::forUser($request->user())->authorize('delete', $item);

        $item->delete();

        return Response::text("Removed \"{$item->name}\" from the list \"{$checklist->name}\".");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'checklist_id' => $schema->integer()
                ->description('The id of the checklist the item belongs to.')
                ->required(),
            'item_id' => $schema->integer()
                ->description('The id of the item to remove.')
                ->required(),
        ];
    }
}
