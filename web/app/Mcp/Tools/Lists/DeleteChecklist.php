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
#[Description('Delete a checklist, along with its items, from the current team by id.')]
class DeleteChecklist extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $checklist = $request->user()->currentTeam->checklists()->find($validated['id']);

        if ($checklist === null) {
            return Response::error('Checklist not found.');
        }

        Gate::forUser($request->user())->authorize('delete', $checklist);

        $checklist->delete();

        return Response::text("The list \"{$checklist->name}\" has been deleted.");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the checklist to delete.')
                ->required(),
        ];
    }
}
