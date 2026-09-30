<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ChecklistType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('checklist'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(ChecklistType::class)],
            'user_id' => ['nullable', 'integer', Rule::in($this->route('team')->members()->pluck('users.id'))],
        ];
    }
}
