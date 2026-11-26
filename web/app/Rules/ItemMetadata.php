<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ItemMetadata implements ValidationRule
{
    public const int MAX_DETAILS = 50;

    public const int MAX_LENGTH = 255;

    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $fail(__('The details must be labels with text values.'));

            return;
        }

        if (count($value) > self::MAX_DETAILS) {
            $fail(__('An item can have at most :max details.', ['max' => self::MAX_DETAILS]));

            return;
        }

        if (! array_all($value, fn (mixed $detail): bool => is_string($detail))) {
            $fail(__('Each detail must be text.'));

            return;
        }

        if (array_any(array_keys($value), fn (int|string $label): bool => trim((string) $label) === '')) {
            $fail(__('Each detail needs a label.'));

            return;
        }

        $tooLong = array_any(
            $value,
            fn (string $detail, int|string $label): bool => mb_strlen((string) $label) > self::MAX_LENGTH || mb_strlen($detail) > self::MAX_LENGTH,
        );

        if ($tooLong) {
            $fail(__('Each detail label and value must be at most :max characters.', ['max' => self::MAX_LENGTH]));
        }
    }
}
