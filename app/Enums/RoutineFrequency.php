<?php

declare(strict_types=1);

namespace App\Enums;

enum RoutineFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => __('Daily'),
            self::Weekly => __('Weekly'),
            self::Monthly => __('Monthly'),
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
