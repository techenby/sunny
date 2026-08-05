<?php

declare(strict_types=1);

namespace App\Enums;

enum RoutineCadence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => __('Daily'),
            self::Weekly => __('Weekly'),
        };
    }
}
