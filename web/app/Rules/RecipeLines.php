<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class RecipeLines implements ValidationRule
{
    public const int MAX_CHARACTERS = 20_000;

    public const int MAX_ENTRIES = 500;

    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            if (mb_strlen($value) > self::MAX_CHARACTERS) {
                $fail(__('The :attribute is too long.'));
            }

            return;
        }

        $isList = is_array($value) && array_is_list($value) && array_all(
            $value,
            fn (mixed $entry): bool => is_string($entry) || (is_array($entry) && is_string($entry['text'] ?? null)),
        );

        if (! $isList) {
            $fail(__('The :attribute must be text with one entry per line, or a list of entries.'));

            return;
        }

        if (count($value) > self::MAX_ENTRIES || strlen((string) json_encode($value)) > self::MAX_CHARACTERS * 4) {
            $fail(__('The :attribute is too long.'));
        }
    }
}
