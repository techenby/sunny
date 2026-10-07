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
#[Description('Permanently remove every completed item from a checklist on the current team, leaving only the incomplete ones. To uncheck items instead of removing them, use the reset-checklist tool.')]
class ClearCompletedChecklistItems extends Tool
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

        Gate::forUser($request->user())->authorize('update', $checklist);

        $count = $checklist->items()->completed()->delete();

        return Response::text(sprintf(
            'Removed %d completed %s from the list "%s".',
            $count,
            $count === 1 ? 'item' : 'items',
            $checklist->name,
        ));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the checklist to clear completed items from.')
                ->required(),
        ];
    }
}
