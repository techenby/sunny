<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Lists;

use App\Mcp\Tools\Lists\Concerns\FormatsChecklists;
use App\Models\ChecklistItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add one or more items to the end of a checklist on the current team, e.g. ["milk", "eggs"] on a shopping list. New items start incomplete.')]
class AddChecklistItems extends Tool
{
    use FormatsChecklists;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'checklist_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'string', 'max:255'],
        ], [
            'items.required' => 'Provide at least one item name to add.',
            'items.min' => 'Provide at least one item name to add.',
            'items.*.required' => 'Each item must be a non-empty name.',
            'items.*.string' => 'Each item must be a name given as a string.',
            'items.*.max' => 'Each item name may not be longer than 255 characters.',
        ]);

        $checklist = $request->user()->currentTeam->checklists()->find($validated['checklist_id']);

        if ($checklist === null) {
            return Response::error('Checklist not found.');
        }

        Gate::forUser($request->user())->authorize('update', $checklist);

        $items = DB::transaction(fn (): array => array_map(
            fn (string $name): ChecklistItem => $checklist->items()->create(['name' => $name]),
            $validated['items'],
        ));

        return Response::structured([
            'checklist_id' => $checklist->id,
            'count' => count($items),
            'items' => array_map(fn (ChecklistItem $item): array => $this->formatChecklistItem($item), $items),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'checklist_id' => $schema->integer()
                ->description('The id of the checklist to add items to. Use the list-checklists tool to find ids.')
                ->required(),
            'items' => $schema->array()
                ->items($schema->string()->max(255))
                ->min(1)
                ->description('The names of the items to add, in order.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'checklist_id' => $schema->integer()->required(),
            'count' => $schema->integer()->required(),
            'items' => $schema->array()->items($this->checklistItemOutputSchema($schema))->required()
                ->description('The newly created items.'),
        ];
    }
}
