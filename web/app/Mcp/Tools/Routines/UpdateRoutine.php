<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Mcp\Tools\Routines\Concerns\FormatsRoutines;
use App\Mcp\Tools\Routines\Concerns\ValidatesRoutineSchedules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update a routine on the current team. Only the provided fields are changed. Pause a routine with is_active false and resume it with is_active true. When changing frequency, also pass weekdays (weekly) or day_of_month (monthly); fields the new frequency does not use are cleared. Use the add-routine-steps, update-routine-step, reorder-routine-steps, and remove-routine-step tools to change its steps.')]
class UpdateRoutine extends Tool
{
    use FormatsRoutines;
    use ValidatesRoutineSchedules;

    public function handle(Request $request): Response|ResponseFactory
    {
        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::in($team->members()->pluck('users.id'))],
            'time_of_day' => ['sometimes', 'required', Rule::enum(TimeOfDay::class)],
            'frequency' => ['sometimes', 'required', Rule::enum(RoutineFrequency::class)],
            'weekdays' => [Rule::requiredIf($request->get('frequency') === RoutineFrequency::Weekly->value), 'nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'day_of_month' => [Rule::requiredIf($request->get('frequency') === RoutineFrequency::Monthly->value), 'nullable', 'integer', 'between:1,31'],
            'starts_on' => ['sometimes', 'required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->scheduleMessages());

        $routine = $team->routines()->find($validated['id']);

        if (! $routine) {
            return Response::error('Routine not found.');
        }

        Gate::forUser($request->user())->authorize('update', $routine);

        $data = Arr::except($validated, ['id']);

        if ($data === []) {
            return Response::error('Provide at least one field to update.');
        }

        $routine->update($this->normalizeSchedule($data));

        return Response::structured($this->routineWithStepsPayload($routine->load(['user', 'steps'])));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the routine to update. Use the list-routines tool to find ids.')
                ->required(),
            'name' => $schema->string()
                ->max(255)
                ->description('A new name for the routine.'),
            'user_id' => $schema->integer()
                ->nullable()
                ->description('The id of the team member the routine belongs to, or null to make it a household routine.'),
            'time_of_day' => $schema->string()
                ->enum(TimeOfDay::class)
                ->description('When the routine happens: "morning", "afternoon", "evening", or "anytime".'),
            'frequency' => $schema->string()
                ->enum(RoutineFrequency::class)
                ->description('How often the routine repeats: "daily", "weekly", or "monthly".'),
            'weekdays' => $schema->array()
                ->items($schema->integer()->min(0)->max(6))
                ->description('For weekly routines: the days it runs, as integers where 0 is Sunday and 6 is Saturday. Required when changing frequency to weekly.'),
            'day_of_month' => $schema->integer()
                ->min(1)
                ->max(31)
                ->description('For monthly routines: the day of the month it runs on. Required when changing frequency to monthly.'),
            'starts_on' => $schema->string()
                ->description('The first date the routine runs, as Y-m-d.'),
            'is_active' => $schema->boolean()
                ->description('Pass false to pause the routine or true to resume it.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->routineWithStepsSchema($schema);
    }
}
