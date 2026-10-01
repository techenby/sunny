<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRoutineRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::in($this->route('team')->members()->pluck('users.id'))],
            'time_of_day' => ['sometimes', 'required', Rule::enum(TimeOfDay::class)],
            'frequency' => ['sometimes', 'required', Rule::enum(RoutineFrequency::class)],
            'weekdays' => [Rule::requiredIf($this->input('frequency') === RoutineFrequency::Weekly->value), 'nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'day_of_month' => [Rule::requiredIf($this->input('frequency') === RoutineFrequency::Monthly->value), 'nullable', 'integer', 'between:1,31'],
            'starts_on' => ['sometimes', 'required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
