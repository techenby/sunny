<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Mcp\Tools\Routines\Concerns\FormatsRoutines;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Rename a step on a routine on the current team. Use the reorder-routine-steps tool to change its position.')]
class UpdateRoutineStep extends Tool
{
    use FormatsRoutines;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'routine_id' => ['required', 'integer'],
            'step_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
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

        $step->update(['name' => $validated['name']]);

        return Response::structured($this->stepPayload($step));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'routine_id' => $schema->integer()
                ->description('The id of the routine the step belongs to.')
                ->required(),
            'step_id' => $schema->integer()
                ->description('The id of the step to rename. Use the get-routine tool to find step ids.')
                ->required(),
            'name' => $schema->string()
                ->max(255)
                ->description('The new name for the step.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->stepSchema($schema);
    }
}
