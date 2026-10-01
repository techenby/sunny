<?php

declare(strict_types=1);

namespace App\Actions\Routines;

use App\Enums\RoutineFrequency;
use App\Models\Routine;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateRoutine
{
    public function __construct(private readonly SyncRoutineSteps $steps) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): Routine
    {
        $steps = Arr::pull($data, 'steps');

        $frequency = RoutineFrequency::from($data['frequency']);

        $data['weekdays'] = $frequency->usesWeekdays() ? array_values($data['weekdays']) : null;
        $data['day_of_month'] = $frequency->usesDayOfMonth() ? $data['day_of_month'] : null;
        $data['starts_on'] ??= $team->today()->toDateString();
        $data['is_active'] ??= true;

        return DB::transaction(function () use ($team, $data, $steps): Routine {
            $routine = $team->routines()->create($data);

            if ($steps !== null) {
                $this->steps->handle($routine, $steps);
            }

            return $routine;
        });
    }
}
