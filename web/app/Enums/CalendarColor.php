<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarColor: string
{
    case Blue = '#2563eb';
    case Green = '#16a34a';
    case Red = '#dc2626';
    case Purple = '#9333ea';
    case Orange = '#ea580c';
    case Cyan = '#0891b2';
    case Gold = '#ca8a04';
    case Indigo = '#4f46e5';

    public const string LightText = '#ffffff';

    public const string DarkText = '#09090b';

    public function textColor(): string
    {
        return $this->contrastWith(self::LightText) >= $this->contrastWith(self::DarkText)
            ? self::LightText
            : self::DarkText;
    }

    public function contrastWith(string $hex): float
    {
        $lighter = max(self::luminance($this->value), self::luminance($hex));
        $darker = min(self::luminance($this->value), self::luminance($hex));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
