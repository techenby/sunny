<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarEventStyle: string
{
    case Stripe = 'stripe';
    case Tinted = 'tinted';
    case Filled = 'filled';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => __('Stripe'),
            self::Tinted => __('Tinted'),
            self::Filled => __('Filled'),
        };
    }
}
