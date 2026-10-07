<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines\Concerns;

use App\Enums\RoutineFrequency;

trait ValidatesRoutineSchedules
{
    /** @return array<string, string> */
    protected function scheduleMessages(): array
    {
        return [
            'user_id.in' => 'The user_id must be a member of the current team, or null for a household routine.',
            'time_of_day.enum' => 'The time_of_day must be one of: morning, afternoon, evening, anytime.',
            'frequency.enum' => 'The frequency must be one of: daily, weekly, monthly.',
            'weekdays.required' => 'Weekly routines need at least one weekday, as integers where 0 is Sunday and 6 is Saturday.',
            'weekdays.*.integer' => 'Weekdays must be integers where 0 is Sunday and 6 is Saturday.',
            'weekdays.*.between' => 'Weekdays must be integers where 0 is Sunday and 6 is Saturday.',
            'weekdays.*.distinct' => 'Each weekday may only be listed once.',
            'day_of_month.required' => 'Monthly routines need a day_of_month between 1 and 31.',
            'day_of_month.between' => 'The day_of_month must be between 1 and 31. Days past the end of a short month run on its last day.',
            'starts_on.date' => 'The starts_on argument must be a valid date, for example "2026-07-08".',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeSchedule(array $data): array
    {
        if (! isset($data['frequency'])) {
            return $data;
        }

        $frequency = RoutineFrequency::from($data['frequency']);

        return [
            ...$data,
            'weekdays' => $frequency->usesWeekdays() ? array_map(intval(...), $data['weekdays']) : null,
            'day_of_month' => $frequency->usesDayOfMonth() ? $data['day_of_month'] : null,
        ];
    }
}
