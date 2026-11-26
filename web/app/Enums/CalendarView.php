<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarView: string
{
    case Automatic = 'automatic';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => __('Automatic'),
            self::Day => __('Day'),
            self::Week => __('Week'),
            self::Month => __('Month'),
        };
    }
}
