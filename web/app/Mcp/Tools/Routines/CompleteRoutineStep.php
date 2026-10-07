<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Mcp\Tools\Routines\Concerns\FormatsRoutineOccurrences;
use App\Models\RoutineOccurrenceStep;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Mark a step on the routine board as completed by the current user, or pass completed false to undo it. Use the get-routine-board tool to find occurrence_step_id values. Returns the step and the updated progress of its routine for that day.')]
class CompleteRoutineStep extends Tool
{
    use FormatsRoutineOccurrences;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'occurrence_step_id' => ['required', 'integer'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $team = $request->user()->currentTeam;

        $step = RoutineOccurrenceStep::query()
            ->whereHas('occurrence.routine', fn (Builder $query) => $query->where('team_id', $team->id))
            ->with('occurrence.routine')
            ->find($validated['occurrence_step_id']);

        if (! $step) {
            return Response::error('Routine step not found.');
        }

        $occurrence = $step->occurrence;

        Gate::forUser($request->user())->authorize('complete', $occurrence->routine);

        $completed = (bool) ($validated['completed'] ?? true);

        if ($completed !== $step->isCompleted()) {
            $completed ? $step->complete($request->user()) : $step->uncomplete();
        }

        $occurrence->load(['routine.user', 'steps.step', 'steps.completedBy']);
        $payload = $this->occurrencePayload($occurrence, $team->timezone);

        return Response::structured([
            'step' => collect($payload['steps'])->firstWhere('occurrence_step_id', $step->id),
            'occurrence' => Arr::except($payload, ['steps']),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'occurrence_step_id' => $schema->integer()
                ->description('The occurrence_step_id of the step, from the get-routine-board tool.')
                ->required(),
            'completed' => $schema->boolean()
                ->default(true)
                ->description('True to mark the step done (the default), false to mark it not done.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'step' => $schema->object($this->occurrenceStepSchema($schema))->required(),
            'occurrence' => $schema->object(Arr::except($this->occurrenceSchema($schema), ['steps']))->required()
                ->description("The step's routine for that day, with its updated progress."),
        ];
    }
}
