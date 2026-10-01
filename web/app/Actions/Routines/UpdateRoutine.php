<?php

declare(strict_types=1);

namespace App\Actions\Routines;

use App\Enums\RoutineFrequency;
use App\Models\Routine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateRoutine
{
    public function __construct(private readonly SyncRoutineSteps $steps) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Routine $routine, array $data): Routine
    {
        $steps = Arr::pull($data, 'steps');

        $frequency = RoutineFrequency::from($data['frequency'] ?? $routine->frequency->value);

        $data['weekdays'] = $frequency->usesWeekdays()
            ? array_values($data['weekdays'] ?? $routine->scheduledWeekdays())
            : null;
        $data['day_of_month'] = $frequency->usesDayOfMonth()
            ? ($data['day_of_month'] ?? $routine->day_of_month)
            : null;

        return DB::transaction(function () use ($routine, $data, $steps): Routine {
            $routine->update($data);

            if ($steps !== null) {
                $this->steps->handle($routine, $steps);
            }

            return $routine;
        });
    }
}
