<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /** @return array<string, array<int, ValidationRule|array<mixed>|string>> */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'timezone' => $this->timezoneRules(),
        ];
    }

    /** @return array<int, ValidationRule|array<mixed>|string> */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /** @return array<int, ValidationRule|array<mixed>|string> */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /** @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string> */
    protected function timezoneRules(): array
    {
        return ['required', 'string', Rule::in(timezone_identifiers_list())];
    }
}
