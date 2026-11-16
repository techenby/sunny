<?php

declare(strict_types=1);

namespace App\Support\Recipes;

final class Quantity
{
    // One number as written in a recipe. The alternatives are tried in this order,
    // so the longer forms win over the shorter ones they contain:
    //   mixed number           "1 1/2"
    //   fraction               "3/4"
    //   whole plus a glyph     "1½" or "1 ½"
    //   whole or decimal       "2" or "1.5"
    //   glyph on its own       "½"
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
