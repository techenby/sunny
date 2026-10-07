<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a routine from the current team by id. To stop a routine temporarily, pause it with the update-routine tool instead.')]
class DeleteRoutine extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $routine = $request->user()->currentTeam->routines()->find($validated['id']);

        if (! $routine) {
            return Response::error('Routine not found.');
        }

        Gate::forUser($request->user())->authorize('delete', $routine);

        $routine->delete();

        return Response::text("The routine \"{$routine->name}\" has been deleted.");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the routine to delete.')
                ->required(),
        ];
    }
}
