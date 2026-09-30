<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ChecklistType;
use App\Models\Checklist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Checklist::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ChecklistType::class)],
            'user_id' => ['nullable', 'integer', Rule::in($this->route('team')->members()->pluck('users.id'))],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
