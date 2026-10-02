<?php

declare(strict_types=1);

namespace App\Actions\Routines;

use App\Enums\RoutineFrequency;
use App\Models\Routine;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveRoutine
{
    /**
     * Create a routine, or update the given one, along with its steps when
     * they're included.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data, ?Routine $routine = null): Routine
    {
        $steps = Arr::pull($data, 'steps');

        // Only persist the fields the chosen frequency actually reads, so a
        // routine switched from weekly to daily doesn't keep stale weekdays.
        if (isset($data['frequency'])) {
            $frequency = RoutineFrequency::from($data['frequency']);
            $data['weekdays'] = $frequency->usesWeekdays() ? array_values($data['weekdays'] ?? []) : null;
            $data['day_of_month'] = $frequency->usesDayOfMonth() ? $data['day_of_month'] ?? null : null;
        }

        return DB::transaction(function () use ($team, $data, $steps, $routine): Routine {
            if ($routine) {
                $routine->update($data);
            } else {
                $routine = $team->routines()->create([
                    ...$data,
                    'starts_on' => $data['starts_on'] ?? $team->today()->toDateString(),
                ]);
            }

            if ($steps !== null) {
                $this->syncSteps($routine, $steps);
            }

            return $routine->load(['user', 'steps']);
        });
    }

    /**
     * Make the routine's steps match the given list: steps with an `id` are
     * renamed, steps without one are added, and steps left out are removed.
     * The list order becomes the step order.
     *
     * @param  array<int, array{id?: int, name: string}>  $steps
     */
    private function syncSteps(Routine $routine, array $steps): void
    {
        $existing = $routine->steps()->get()->keyBy('id');

        $existing->except(Arr::pluck($steps, 'id'))->each->delete();

        foreach (array_values($steps) as $index => $step) {
            $attributes = ['name' => $step['name'], 'position' => $index + 1];

            isset($step['id'])
                ? $existing[$step['id']]->update($attributes)
                : $routine->steps()->create($attributes);
        }
    }
}
