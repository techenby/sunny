<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Mcp\Tools\Routines\Concerns\FormatsRoutines;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description("Put a routine's steps in a new order. Pass every one of the routine's current step ids, in the desired order; use the get-routine tool to find them.")]
class ReorderRoutineSteps extends Tool
{
    use FormatsRoutines;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'routine_id' => ['required', 'integer'],
            'step_ids' => ['required', 'array', 'min:1'],
            'step_ids.*' => ['required', 'integer', 'distinct'],
        ], [
            'step_ids.*.distinct' => 'Each step id may only be listed once.',
        ]);

        $routine = $request->user()->currentTeam->routines()->find($validated['routine_id']);

        if (! $routine) {
            return Response::error('Routine not found.');
        }

        Gate::forUser($request->user())->authorize('update', $routine);

        $steps = $routine->steps()->get()->keyBy('id');
        $stepIds = array_map(intval(...), $validated['step_ids']);

        if (count($stepIds) !== $steps->count() || array_diff($stepIds, $steps->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'step_ids' => sprintf(
                    'The step_ids must contain exactly the routine\'s current step ids, each once: [%s].',
                    $steps->keys()->implode(', '),
                ),
            ]);
        }

        DB::transaction(function () use ($stepIds, $steps): void {
            foreach ($stepIds as $index => $stepId) {
                $steps[$stepId]->update(['position' => $index + 1]);
            }
        });

        return Response::structured([
            'routine_id' => $routine->id,
            'steps' => collect($stepIds)
                ->map(fn (int $stepId): array => $this->stepPayload($steps[$stepId]))
                ->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'routine_id' => $schema->integer()
                ->description('The id of the routine whose steps to reorder.')
                ->required(),
            'step_ids' => $schema->array()
                ->items($schema->integer())
                ->min(1)
                ->description('All of the routine\'s step ids, in the desired order, e.g. [12, 10, 11].')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'routine_id' => $schema->integer()->required(),
            'steps' => $schema->array()->items($schema->object($this->stepSchema($schema)))->required()
                ->description('The steps in their new order.'),
        ];
    }
}
