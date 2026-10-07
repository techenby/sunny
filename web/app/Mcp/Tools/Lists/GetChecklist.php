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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get a single checklist from the current team by id, including its items in position order and who completed each one. Use the list-checklists tool to find ids.')]
class GetChecklist extends Tool
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

        Gate::forUser($request->user())->authorize('view', $checklist);

        return Response::structured($this->formatChecklist($this->loadChecklistDetails($checklist)));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the checklist to fetch.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->checklistOutputSchema($schema);
    }
}
