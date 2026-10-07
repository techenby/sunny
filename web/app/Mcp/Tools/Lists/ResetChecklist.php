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
#[Description('Mark every item on a checklist as incomplete so the list can be reused, e.g. a weekly chores list. Items are kept; to remove completed items instead, use the clear-completed-checklist-items tool.')]
class ResetChecklist extends Tool
{
    use FormatsChecklists;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $checklist = $request->user()->currentTeam->checklists()->find($validated['id']);

        if ($checklist === null) {
            return Response::error('Checklist not found.');
        }

        Gate::forUser($request->user())->authorize('update', $checklist);

        $checklist->reset();

        return Response::structured($this->formatChecklist($this->loadChecklistDetails($checklist)));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the checklist to reset.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->checklistOutputSchema($schema);
    }
}
