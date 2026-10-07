<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Mcp\Tools\Routines\Concerns\FormatsRoutines;
use App\Mcp\Tools\Routines\Concerns\ValidatesRoutineSchedules;
use App\Models\Routine;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a routine on the current team, optionally with its steps. A routine belongs to one team member (user_id) or to the whole household (user_id null), happens at a time of day, and repeats daily, weekly on chosen weekdays, or monthly on a day of the month. It starts today unless starts_on is given.')]
class CreateRoutine extends Tool
{
    use FormatsRoutines;
    use ValidatesRoutineSchedules;

    public function handle(Request $request): ResponseFactory
    {
        Gate::forUser($request->user())->authorize('create', Routine::class);

        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::in($team->members()->pluck('users.id'))],
            'time_of_day' => ['required', Rule::enum(TimeOfDay::class)],
            'frequency' => ['required', Rule::enum(RoutineFrequency::class)],
            'weekdays' => [Rule::requiredIf($request->get('frequency') === RoutineFrequency::Weekly->value), 'nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'day_of_month' => [Rule::requiredIf($request->get('frequency') === RoutineFrequency::Monthly->value), 'nullable', 'integer', 'between:1,31'],
            'starts_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['sometimes', 'array'],
            'steps.*' => ['required', 'string', 'max:255'],
        ], $this->scheduleMessages());

        $routine = DB::transaction(function () use ($team, $validated): Routine {
            $routine = $team->routines()->create([
                ...$this->normalizeSchedule(Arr::except($validated, ['steps'])),
                'starts_on' => $validated['starts_on'] ?? $team->today()->toDateString(),
            ]);

            foreach ($validated['steps'] ?? [] as $name) {
                $routine->steps()->create(['name' => $name]);
            }

            return $routine;
        });

        return Response::structured($this->routineWithStepsPayload($routine->fresh(['user', 'steps'])));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->max(255)
                ->description('The name of the routine, e.g. "Morning routine" or "Bedtime".')
                ->required(),
            'user_id' => $schema->integer()
                ->nullable()
                ->description('The id of the team member the routine belongs to. Omit or pass null for a household routine anyone can complete.'),
            'time_of_day' => $schema->string()
                ->enum(TimeOfDay::class)
                ->description('When the routine happens: "morning", "afternoon", "evening", or "anytime".')
                ->required(),
            'frequency' => $schema->string()
                ->enum(RoutineFrequency::class)
                ->description('How often the routine repeats: "daily", "weekly", or "monthly".')
                ->required(),
            'weekdays' => $schema->array()
                ->items($schema->integer()->min(0)->max(6))
                ->description('Required for weekly routines: the days it runs, as integers where 0 is Sunday and 6 is Saturday, e.g. [1, 3, 5] for Monday, Wednesday, and Friday. Ignored for other frequencies.'),
            'day_of_month' => $schema->integer()
                ->min(1)
                ->max(31)
                ->description('Required for monthly routines: the day of the month it runs on. Ignored for other frequencies.'),
            'starts_on' => $schema->string()
                ->description('The first date the routine runs, as Y-m-d. Defaults to today in the team\'s timezone.'),
            'is_active' => $schema->boolean()
                ->default(true)
                ->description('Pass false to create the routine paused.'),
            'steps' => $schema->array()
                ->items($schema->string()->max(255))
                ->description('The names of the routine\'s steps, in order, e.g. ["Brush teeth", "Get dressed"].'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->routineWithStepsSchema($schema);
    }
}
