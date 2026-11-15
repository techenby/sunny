<?php

declare(strict_types=1);

namespace App\Support\Recipes;

final class Quantity
{
    public const string PATTERN = '(?:\d+\s+\d+\/\d+|\d+\/\d+|\d+\s?[½⅓⅔¼¾⅕⅖⅗⅘⅙⅚⅛⅜⅝⅞]|\d+(?:\.\d+)?|[½⅓⅔¼¾⅕⅖⅗⅘⅙⅚⅛⅜⅝⅞])';

    private const array FRACTIONS = [
        '½' => 1 / 2, '⅓' => 1 / 3, '⅔' => 2 / 3, '¼' => 1 / 4, '¾' => 3 / 4,
        '⅕' => 1 / 5, '⅖' => 2 / 5, '⅗' => 3 / 5, '⅘' => 4 / 5, '⅙' => 1 / 6,
        '⅚' => 5 / 6, '⅛' => 1 / 8, '⅜' => 3 / 8, '⅝' => 5 / 8, '⅞' => 7 / 8,
    ];

    public static function toFloat(string $value): ?float
    {
        $value = trim($value);
        $total = 0.0;

        foreach (self::FRACTIONS as $glyph => $fraction) {
            if (str_contains($value, $glyph)) {
                $total += $fraction;
                $value = trim(str_replace($glyph, '', $value));
            }
        }

        foreach (preg_split('/\s+/', $value, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (str_contains($part, '/')) {
                [$numerator, $denominator] = explode('/', $part, 2);

                if ((float) $denominator === 0.0) {
                    return null;
                }

                $total += (float) $numerator / (float) $denominator;
            } else {
                $total += (float) $part;
            }
        }

        return round($total, 4);
    }
}
