<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Mcp\Tools\Routines\Concerns\FormatsRoutines;
use App\Models\RoutineStep;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add one or more steps to the end of a routine on the current team, in the order given.')]
class AddRoutineSteps extends Tool
{
    use FormatsRoutines;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'routine_id' => ['required', 'integer'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => ['required', 'string', 'max:255'],
        ], [
            'steps.required' => 'Provide the names of the steps to add as an array of strings, e.g. ["Brush teeth"].',
        ]);

        $routine = $request->user()->currentTeam->routines()->find($validated['routine_id']);

        if (! $routine) {
            return Response::error('Routine not found.');
        }

        Gate::forUser($request->user())->authorize('update', $routine);

        $steps = DB::transaction(fn () => collect($validated['steps'])
            ->map(fn (string $name): RoutineStep => $routine->steps()->create(['name' => $name])));

        return Response::structured([
            'routine_id' => $routine->id,
            'steps' => $steps->map(fn (RoutineStep $step): array => $this->stepPayload($step))->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'routine_id' => $schema->integer()
                ->description('The id of the routine to add steps to. Use the list-routines tool to find ids.')
                ->required(),
            'steps' => $schema->array()
                ->items($schema->string()->max(255))
                ->min(1)
                ->description('The names of the steps to add, in order, e.g. ["Brush teeth", "Get dressed"].')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'routine_id' => $schema->integer()->required(),
            'steps' => $schema->array()->items($schema->object($this->stepSchema($schema)))->required()
                ->description('The steps that were created.'),
        ];
    }
}
