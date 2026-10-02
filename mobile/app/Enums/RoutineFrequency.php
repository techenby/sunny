<?php

namespace App\Enums;

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

    public function usesWeekdays(): bool
    {
        return $this === self::Weekly;
    }

    public function usesDayOfMonth(): bool
    {
        return $this === self::Monthly;
    }
}
