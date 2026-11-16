<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Recipe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ImportRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Recipe::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:500'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
