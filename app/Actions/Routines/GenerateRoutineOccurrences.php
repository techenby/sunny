<?php

declare(strict_types=1);

namespace App\Actions\Routines;

use Illuminate\Contracts\Database\Query\Builder;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Materialises the occurrence and step rows a routine needs for a given day.
 *
 * Every write is idempotent, so the board can call this on render and the
 * nightly command can call it again without duplicating anything.
 */
class GenerateRoutineOccurrences
{
    /**
     * Generate occurrences for a team from one date through another, inclusive.
     * Omitting the end date generates a single day.
     *
     * @return int the number of occurrences created
     */
    public function handle(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $through = null): int
    {
        $from = CarbonImmutable::parse($from ?? $team->today())->startOfDay();
        $through = CarbonImmutable::parse($through ?? $from)->startOfDay();

        if ($through->lt($from)) {
            return 0;
        }

        $routines = $team->routines()->active()->with('steps')->get();

        if ($routines->isEmpty()) {
            return 0;
        }

        $created = 0;

        foreach ($from->toPeriod($through) as $date) {
            foreach ($routines as $routine) {
                if (! $routine->occursOn($date)) {
                    continue;
                }

                $created += $this->generate($routine, $date) ? 1 : 0;
            }
        }

        return $created;
    }

    /**
     * Generate the next `$days` of occurrences for every team, starting today.
     *
     * @return int the number of occurrences created
     */
    public function warm(int $days = 7): int
    {
        return Team::query()
            ->lazyById()
            ->sum(fn (Team $team): int => $this->handle(
                $team,
                $team->today(),
                $team->today()->addDays($days - 1),
            ));
    }

    /**
     * The occurrences a team should show for a date, generating them first.
     *
     * @return Collection<int, RoutineOccurrence>
     */
    public function forDate(Team $team, ?CarbonInterface $date = null): Collection
    {
        $date = CarbonImmutable::parse($date ?? $team->today())->startOfDay();

        $this->handle($team, $date);

        // Pausing a routine should clear it from today onward without rewriting
        // history, so is_active only filters dates that haven't happened yet.
        $isPast = $date->lt($team->today());

        return RoutineOccurrence::query()
            ->whereHas('routine', fn (Builder $query) => $query
                ->where('team_id', $team->id)
                ->unless($isPast, fn ($query) => $query->where('is_active', true)))
            ->with(['routine.user', 'steps.step'])
            ->due($date)
            ->get()
            ->sortBy(fn (RoutineOccurrence $occurrence): array => [
                $occurrence->routine->time_of_day->getSortOrder(),
                $occurrence->routine->name,
            ])
            ->values();
    }

    /**
     * @return bool whether the occurrence itself was newly created
     */
    private function generate(Routine $routine, CarbonInterface $date): bool
    {
        $occurrence = RoutineOccurrence::firstOrCreate(
            [
                'routine_id' => $routine->id,
                'due_on' => $date->toDateString(),
            ],
            [
                'generated_at' => now(),
            ],
        );

        $this->generateSteps($occurrence, $routine);

        return $occurrence->wasRecentlyCreated;
    }

    /**
     * Add a row for every step the occurrence is missing. Steps added to a
     * routine after the fact land here on the next pass; steps removed from it
     * are soft deleted, so they stop generating while past rows survive.
     */
    private function generateSteps(RoutineOccurrence $occurrence, Routine $routine): void
    {
        if ($routine->steps->isEmpty()) {
            return;
        }

        $existing = $occurrence->steps()->pluck('routine_step_id');

        $missing = $routine->steps
            ->reject(fn ($step): bool => $existing->contains($step->id))
            ->map(fn ($step): array => [
                'routine_occurrence_id' => $occurrence->id,
                'routine_step_id' => $step->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if ($missing->isEmpty()) {
            return;
        }

        RoutineOccurrenceStep::insertOrIgnore($missing->all());
    }
}
