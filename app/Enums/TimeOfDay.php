<?php

declare(strict_types=1);

namespace App\Enums;

enum TimeOfDay: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case Anytime = 'anytime';

    public function getIcon(): string
    {
        return match ($this) {
            self::Morning => 'sunrise',
            self::Afternoon => 'sun',
            self::Evening => 'sunset',
            self::Anytime => 'clock',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Morning => __('Morning'),
            self::Afternoon => __('Afternoon'),
            self::Evening => __('Evening'),
            self::Anytime => __('Anytime'),
        };
    }

    public function getSortOrder(): int
    {
        return match ($this) {
            self::Morning => 1,
            self::Afternoon => 2,
            self::Evening => 3,
            self::Anytime => 4,
        };
    }
}
