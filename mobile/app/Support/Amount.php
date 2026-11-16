<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Mirrors App\Support\Recipes\Amount in the sunnyhome.app web app, so a scaled
 * ingredient reads the same in both places.
 */
class Amount
{
    private const ABBREVIATIONS = ['tsp', 'oz', 'lb', 'g', 'kg', 'ml', 'l'];

    private const DECIMAL_UNITS = ['g', 'kg', 'ml', 'l'];

    /**
     * @param  array<string, mixed>  $row
     * @return array{amount: string|null, text: string}
     */
    public static function scale(array $row, float $factor = 1.0): array
    {
        $text = (string) ($row['text'] ?? '');
        $quantity = $row['quantity'] ?? null;

        if (! is_numeric($quantity)) {
            return ['amount' => null, 'text' => $text];
        }

        $unit = is_string($row['unit'] ?? null) ? $row['unit'] : null;
        $quantity = (float) $quantity * $factor;
        $max = is_numeric($row['quantity_max'] ?? null) ? (float) $row['quantity_max'] * $factor : null;

        $amount = self::number($quantity, $unit);

        if ($max !== null) {
            $amount .= '–'.self::number($max, $unit);
        }

        if ($unit !== null) {
            $amount .= ' '.self::label($unit, plural: max($quantity, $max ?? 0.0) > 1);
        }

        return ['amount' => $amount, 'text' => (string) ($row['rest'] ?? $row['name'] ?? $text)];
    }

    public static function number(float $value, ?string $unit = null): string
    {
        if (in_array($unit, self::DECIMAL_UNITS, true)) {
            return self::decimal($value, 1);
        }

        $whole = (int) floor($value + 0.0001);
        $fraction = $value - $whole;

        if ($fraction < 0.02) {
            return $whole === 0 ? self::decimal($value, 2) : (string) $whole;
        }

        foreach ([2, 3, 4, 8] as $denominator) {
            $numerator = (int) round($fraction * $denominator);

            if (abs($fraction - $numerator / $denominator) > 0.02) {
                continue;
            }

            if ($numerator === $denominator) {
                return (string) ($whole + 1);
            }

            return ($whole === 0 ? '' : "{$whole} ")."{$numerator}/{$denominator}";
        }

        return self::decimal($value, 2);
    }

    private static function label(string $unit, bool $plural): string
    {
        return match (true) {
            $unit === 'tbsp' => 'Tbsp',
            in_array($unit, self::ABBREVIATIONS, true) => $unit,
            default => Str::plural($unit, $plural ? 2 : 1),
        };
    }

    private static function decimal(float $value, int $places): string
    {
        $formatted = number_format($value, $places, '.', '');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
