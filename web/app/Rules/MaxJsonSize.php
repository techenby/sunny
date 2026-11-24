<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class MaxJsonSize implements ValidationRule
{
    public function __construct(private int $bytes) {}

    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen((string) json_encode($value)) > $this->bytes) {
            $fail(__('The :attribute is too large.'));
        }
    }
}
