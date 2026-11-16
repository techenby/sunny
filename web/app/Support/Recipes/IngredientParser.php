<?php

declare(strict_types=1);

namespace App\Support\Recipes;

final class IngredientParser
{
    private const array UNITS = [
        'tsp' => ['tsp', 'tsps', 'teaspoon', 'teaspoons'],
        'tbsp' => ['tbsp', 'tbsps', 'tbs', 'tbl', 'tablespoon', 'tablespoons'],
        'cup' => ['cup', 'cups'],
        'pint' => ['pint', 'pints', 'pt'],
        'quart' => ['quart', 'quarts', 'qt', 'qts'],
        'gallon' => ['gallon', 'gallons', 'gal'],
        'oz' => ['oz', 'ounce', 'ounces'],
        'lb' => ['lb', 'lbs', 'pound', 'pounds'],
        'g' => ['gram', 'grams'],
        'kg' => ['kg', 'kgs', 'kilogram', 'kilograms'],
        'ml' => ['ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres'],
        'l' => ['liter', 'liters', 'litre', 'litres'],
        'pinch' => ['pinch', 'pinches'],
        'dash' => ['dash', 'dashes'],
        'handful' => ['handful', 'handfuls'],
        'splash' => ['splash', 'splashes'],
        'drizzle' => ['drizzle', 'drizzles'],
        'clove' => ['clove', 'cloves'],
        'can' => ['can', 'cans'],
        'stick' => ['stick', 'sticks'],
        'slice' => ['slice', 'slices'],
        'sprig' => ['sprig', 'sprigs'],
        'bunch' => ['bunch', 'bunches'],
        'package' => ['package', 'packages', 'pkg', 'pkgs'],
        'piece' => ['piece', 'pieces'],
    ];

    private const array LETTER_UNITS = [
        'T' => 'tbsp', 't' => 'tsp', 'c' => 'cup', 'C' => 'cup', 'g' => 'g', 'l' => 'l', 'L' => 'l',
    ];

    /** @return array{quantity: float|null, quantity_max: float|null, unit: string|null, name: string} */
    public static function parse(string $text): array
    {
        $text = trim($text);
        $split = self::split($text);

        if ($split === null) {
            return self::withoutAmount($text);
        }

        return [
            'quantity' => $split['quantity'],
            'quantity_max' => $split['quantity_max'],
            'unit' => $split['unit'],
            'name' => self::name($split['rest']),
        ];
    }

    public static function rest(string $text): string
    {
        $text = trim($text);

        return self::split($text)['rest'] ?? $text;
    }

    /** @return array{quantity: float, quantity_max: float|null, unit: string|null, rest: string}|null */
    private static function split(string $text): ?array
    {
        $number = Quantity::PATTERN;

        if (! preg_match("/^({$number})(?:\s*(?:-|–|—|\s+to\s+)\s*({$number}))?(.*)$/su", $text, $matches)) {
            return null;
        }

        if (preg_match('/^(?:x\s*\d|["\'°%-])/iu', $matches[3])) {
            return null;
        }

        $quantity = Quantity::toFloat($matches[1]);
        $max = $matches[2] === '' ? null : Quantity::toFloat($matches[2]);

        if ($quantity === null) {
            return null;
        }

        if ($max !== null && $max < $quantity) {
            $quantity = round($quantity + $max, 4);
            $max = null;
        }

        $unit = null;
        $rest = trim($matches[3]);

        if (preg_match('/^([A-Za-z]+)\.?(?=\s|$)(.*)$/su', $rest, $unitMatches)) {
            $unit = self::unit($unitMatches[1]);

            if ($unit !== null) {
                $rest = trim($unitMatches[2]);
            }
        }

        return ['quantity' => $quantity, 'quantity_max' => $max, 'unit' => $unit, 'rest' => $rest];
    }

    /** @return array{quantity: null, quantity_max: null, unit: string|null, name: string} */
    private static function withoutAmount(string $text): array
    {
        $unit = null;
        $name = $text;

        if (preg_match('/^(?:an?\s+)?(pinch|dash|handful|splash|drizzle)\s+(?:of\s+)?(.+)$/isu', $text, $matches)) {
            $unit = mb_strtolower($matches[1]);
            $name = $matches[2];
        }

        return ['quantity' => null, 'quantity_max' => null, 'unit' => $unit, 'name' => self::name($name)];
    }

    private static function name(string $name): string
    {
        $name = preg_replace('/^of\s+/i', '', trim($name)) ?? $name;

        return preg_replace('/,?\s+\(?to\s+taste\)?$/i', '', $name) ?? $name;
    }

    private static function unit(string $token): ?string
    {
        if (isset(self::LETTER_UNITS[$token])) {
            return self::LETTER_UNITS[$token];
        }

        $token = mb_strtolower($token);

        foreach (self::UNITS as $unit => $aliases) {
            if (in_array($token, $aliases, true)) {
                return $unit;
            }
        }

        return null;
    }
}
