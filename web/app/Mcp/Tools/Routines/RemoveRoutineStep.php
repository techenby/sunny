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
#[Description('Remove a step from a routine on the current team. The step is kept on days already on the routine board, so their history stays accurate, but is not added to new days.')]
class RemoveRoutineStep extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'routine_id' => ['required', 'integer'],
            'step_id' => ['required', 'integer'],
        ]);

        $routine = $request->user()->currentTeam->routines()->find($validated['routine_id']);

        if (! $routine) {
            return Response::error('Routine not found.');
        }

        Gate::forUser($request->user())->authorize('update', $routine);

        $step = $routine->steps()->find($validated['step_id']);

        if (! $step) {
            return Response::error('Step not found on this routine.');
        }

        $step->delete();

        return Response::text("The step \"{$step->name}\" has been removed from the routine \"{$routine->name}\".");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'routine_id' => $schema->integer()
                ->description('The id of the routine the step belongs to.')
                ->required(),
            'step_id' => $schema->integer()
                ->description('The id of the step to remove. Use the get-routine tool to find step ids.')
                ->required(),
        ];
    }
}
