<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines\Concerns;

use App\Enums\TimeOfDay;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use Illuminate\Contracts\JsonSchema\JsonSchema;

trait FormatsRoutineOccurrences
{
    /** @return array<string, mixed> */
    protected function occurrencePayload(RoutineOccurrence $occurrence, string $timezone): array
    {
        $steps = $occurrence->steps
            ->sortBy(fn (RoutineOccurrenceStep $step): array => [$step->step->position, $step->id])
            ->values();

        $completedCount = $steps->filter(fn (RoutineOccurrenceStep $step): bool => $step->isCompleted())->count();

        return [
            'occurrence_id' => $occurrence->id,
            'routine_id' => $occurrence->routine_id,
            'routine_name' => $occurrence->routine->name,
            'time_of_day' => $occurrence->routine->time_of_day->value,
            'owner_name' => $occurrence->routine->user?->name,
            'due_on' => $occurrence->due_on->toDateString(),
            'progress' => $steps->isEmpty() ? 0 : (int) round($completedCount / $steps->count() * 100),
            'completed' => $steps->isNotEmpty() && $completedCount === $steps->count(),
            'steps' => $steps
                ->map(fn (RoutineOccurrenceStep $step): array => $this->occurrenceStepPayload($step, $timezone))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function occurrenceStepPayload(RoutineOccurrenceStep $step, string $timezone): array
    {
        return [
            'occurrence_step_id' => $step->id,
            'name' => $step->step->name,
            'completed' => $step->isCompleted(),
            'completed_at' => $step->completed_at?->setTimezone($timezone)->toIso8601String(),
            'completed_by' => $step->completed_by,
            'completed_by_name' => $step->completedBy?->name,
        ];
    }

    /** @return array<string, JsonSchema> */
    protected function occurrenceSchema(JsonSchema $schema): array
    {
        return [
            'occurrence_id' => $schema->integer()->required(),
            'routine_id' => $schema->integer()->required(),
            'routine_name' => $schema->string()->required(),
            'time_of_day' => $schema->string()->enum(TimeOfDay::class)->required(),
            'owner_name' => $schema->string()->nullable()->required()
                ->description("The routine owner's name, or null for a household routine."),
            'due_on' => $schema->string()->required()
                ->description('The date the occurrence is due, as Y-m-d.'),
            'progress' => $schema->integer()->min(0)->max(100)->required()
                ->description('The percentage of steps completed, from 0 to 100.'),
            'completed' => $schema->boolean()->required()
                ->description('True once every step is completed.'),
            'steps' => $schema->array()->items($schema->object($this->occurrenceStepSchema($schema)))->required()
                ->description('The steps in routine order.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    protected function occurrenceStepSchema(JsonSchema $schema): array
    {
        return [
            'occurrence_step_id' => $schema->integer()->required()
                ->description('Pass this to the complete-routine-step tool to tick the step off.'),
            'name' => $schema->string()->required(),
            'completed' => $schema->boolean()->required(),
            'completed_at' => $schema->string()->nullable()->required()
                ->description("ISO 8601 timestamp in the team's timezone."),
            'completed_by' => $schema->integer()->nullable()->required()
                ->description('The id of the user who completed the step.'),
            'completed_by_name' => $schema->string()->nullable()->required(),
        ];
    }
}
