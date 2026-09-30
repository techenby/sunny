<?php

namespace App\Enums;

use App\Icons\Android;
use App\Icons\Ios;

enum TimeOfDay: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case Anytime = 'anytime';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Morning',
            self::Afternoon => 'Afternoon',
            self::Evening => 'Evening',
            self::Anytime => 'Anytime',
        };
    }

    public function iosIcon(): Ios
    {
        return match ($this) {
            self::Morning => Ios::Sunrise,
            self::Afternoon => Ios::SunMax,
            self::Evening => Ios::Sunset,
            self::Anytime => Ios::Clock,
        };
    }

    public function androidIcon(): Android
    {
        return match ($this) {
            self::Morning => Android::WbTwilight,
            self::Afternoon => Android::WbSunny,
            self::Evening => Android::Bedtime,
            self::Anytime => Android::Schedule,
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Morning => 1,
            self::Afternoon => 2,
            self::Evening => 3,
            self::Anytime => 4,
        };
    }
}
