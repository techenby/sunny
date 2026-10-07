<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines\Concerns;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\Routine;
use App\Models\RoutineStep;
use Illuminate\Contracts\JsonSchema\JsonSchema;

trait FormatsRoutines
{
    /** @return array<string, mixed> */
    protected function routinePayload(Routine $routine): array
    {
        return [
            'id' => $routine->id,
            'name' => $routine->name,
            'user_id' => $routine->user_id,
            'owner_name' => $routine->user?->name,
            'time_of_day' => $routine->time_of_day->value,
            'frequency' => $routine->frequency->value,
            'weekdays' => $routine->weekdays === null ? null : $routine->scheduledWeekdays(),
            'day_of_month' => $routine->day_of_month,
            'starts_on' => $routine->starts_on->toDateString(),
            'is_active' => $routine->is_active,
            'schedule_summary' => $routine->scheduleSummary(),
            'step_count' => $routine->relationLoaded('steps')
                ? $routine->steps->count()
                : (int) $routine->getAttribute('steps_count'),
        ];
    }

    /** @return array<string, mixed> */
    protected function routineWithStepsPayload(Routine $routine): array
    {
        return [
            ...$this->routinePayload($routine),
            'steps' => $routine->steps
                ->map(fn (RoutineStep $step): array => $this->stepPayload($step))
                ->values()
                ->all(),
        ];
    }

    /** @return array{id: int, name: string, position: int} */
    protected function stepPayload(RoutineStep $step): array
    {
        return [
            'id' => $step->id,
            'name' => $step->name,
            'position' => (int) $step->position,
        ];
    }

    /** @return array<string, JsonSchema> */
    protected function routineSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'user_id' => $schema->integer()->nullable()->required()
                ->description('The team member who owns the routine, or null for a household routine.'),
            'owner_name' => $schema->string()->nullable()->required()
                ->description("The owner's name, or null for a household routine."),
            'time_of_day' => $schema->string()->enum(TimeOfDay::class)->required(),
            'frequency' => $schema->string()->enum(RoutineFrequency::class)->required(),
            'weekdays' => $schema->array()->items($schema->integer())->nullable()->required()
                ->description('For weekly routines, the days it runs as integers where 0 is Sunday and 6 is Saturday.'),
            'day_of_month' => $schema->integer()->nullable()->required()
                ->description('For monthly routines, the day of the month it runs on.'),
            'starts_on' => $schema->string()->required()
                ->description('The first date the routine runs, as Y-m-d.'),
            'is_active' => $schema->boolean()->required()
                ->description('False when the routine is paused.'),
            'schedule_summary' => $schema->string()->required(),
            'step_count' => $schema->integer()->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    protected function routineWithStepsSchema(JsonSchema $schema): array
    {
        return [
            ...$this->routineSchema($schema),
            'steps' => $schema->array()->items($schema->object($this->stepSchema($schema)))->required()
                ->description('The steps in order.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    protected function stepSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'position' => $schema->integer()->required(),
        ];
    }
}
