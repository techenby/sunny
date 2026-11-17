<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Unique;

class UpdateProfileRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', ...$this->nameRules()],
            'email' => ['sometimes', ...$this->emailRules($this->user()->id)],
        ];
    }
}
