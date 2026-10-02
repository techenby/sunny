<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRoutineStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('routine'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
