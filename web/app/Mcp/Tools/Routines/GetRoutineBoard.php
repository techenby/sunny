<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Mcp\Tools\Routines\Concerns\FormatsRoutineOccurrences;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("Get the routine board for a day: every routine due that day, ordered by time of day, with each step and whether it has been completed, by whom, and when. Use this to answer questions like \"what's on today's routines?\" or \"did the kids finish their morning routine?\". Defaults to today in the team's timezone. Tick steps off with the complete-routine-step tool.")]
class GetRoutineBoard extends Tool
{
    use FormatsRoutineOccurrences;

    public function handle(Request $request, GenerateRoutineOccurrences $generateRoutineOccurrences): ResponseFactory
    {
        Gate::forUser($request->user())->authorize('viewAny', Routine::class);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'date.date_format' => 'The date must be in Y-m-d format, for example "2026-07-08".',
        ]);

        $team = $request->user()->currentTeam;

        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'], $team->timezone)
            : $team->today();

        $occurrences = Collection::make($generateRoutineOccurrences->forDate($team, $date)->all())
            ->load('steps.completedBy');

        return Response::structured([
            'date' => $date->toDateString(),
            'timezone' => $team->timezone,
            'occurrences' => $occurrences
                ->map(fn (RoutineOccurrence $occurrence): array => $this->occurrencePayload($occurrence, $team->timezone))
                ->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()
                ->description('The day to show, as Y-m-d, for example "2026-07-08". Defaults to today in the team\'s timezone.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->required()
                ->description('The day shown, as Y-m-d.'),
            'timezone' => $schema->string()->required()
                ->description("The team's timezone, which dates and completion times are expressed in."),
            'occurrences' => $schema->array()->items($schema->object($this->occurrenceSchema($schema)))->required()
                ->description('The routines due that day, ordered by time of day and then name.'),
        ];
    }
}
