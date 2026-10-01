<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\Routine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Routine::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::in($this->route('team')->members()->pluck('users.id'))],
            'time_of_day' => ['required', Rule::enum(TimeOfDay::class)],
            'frequency' => ['required', Rule::enum(RoutineFrequency::class)],
            'weekdays' => [Rule::requiredIf($this->input('frequency') === RoutineFrequency::Weekly->value), 'nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'day_of_month' => [Rule::requiredIf($this->input('frequency') === RoutineFrequency::Monthly->value), 'nullable', 'integer', 'between:1,31'],
            'starts_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
