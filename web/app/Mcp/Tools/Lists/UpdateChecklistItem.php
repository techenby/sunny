<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Lists;

use App\Mcp\Tools\Lists\Concerns\FormatsChecklists;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Rename a checklist item, or check it off or uncheck it by passing completed as true or false. Only the provided fields are changed. Use the get-checklist tool to find item ids.')]
class UpdateChecklistItem extends Tool
{
    use FormatsChecklists;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'checklist_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'completed' => ['sometimes', 'boolean'],
        ], [
            'completed.boolean' => 'The completed field must be true or false.',
        ]);

        $checklist = $request->user()->currentTeam->checklists()->find($validated['checklist_id']);

        if ($checklist === null) {
            return Response::error('Checklist not found.');
        }

        $item = $checklist->items()->find($validated['item_id']);

        if ($item === null) {
            return Response::error('Checklist item not found.');
        }

        Gate::forUser($request->user())->authorize('update', $item);

        if (! isset($validated['name']) && ! isset($validated['completed'])) {
            return Response::error('Provide a name, completed, or both to update.');
        }

        if (isset($validated['name'])) {
            $item->update(['name' => $validated['name']]);
        }

        $completed = isset($validated['completed']) ? (bool) $validated['completed'] : null;

        if ($completed !== null && $completed !== $item->isCompleted()) {
            $completed ? $item->complete($request->user()) : $item->uncomplete();
        }

        return Response::structured($this->formatChecklistItem($item->load('completedBy')));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'checklist_id' => $schema->integer()
                ->description('The id of the checklist the item belongs to.')
                ->required(),
            'item_id' => $schema->integer()
                ->description('The id of the item to update.')
                ->required(),
            'name' => $schema->string()
                ->max(255)
                ->description('A new name for the item.'),
            'completed' => $schema->boolean()
                ->description('True to check the item off, false to mark it incomplete again.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->checklistItemOutputProperties($schema);
    }
}
