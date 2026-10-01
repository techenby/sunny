<?php

namespace App\Enums;

/**
 * Mirrors App\Enums\RoutineFrequency in the sunnyhome.app web app.
 */
enum RoutineFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /** The short name of a weekday, where 0 is Sunday. */
    public static function weekdayName(int $day): string
    {
        return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$day] ?? '';
    }
}
