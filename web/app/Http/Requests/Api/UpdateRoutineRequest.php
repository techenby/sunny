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
            'weekdays' => ['required_if:frequency,weekly', 'nullable', 'array', 'list'],
            'weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'day_of_month' => ['required_if:frequency,monthly', 'nullable', 'integer', 'between:1,31'],
            'starts_on' => ['sometimes', 'required', 'date'],
            'is_active' => ['boolean'],
            'steps' => ['array', 'list'],
            'steps.*' => ['array:id,name'],
            'steps.*.id' => [
                'integer',
                'distinct',
                Rule::exists('routine_steps', 'id')->where('routine_id', $this->route('routine')->id)->whereNull('deleted_at'),
            ],
            'steps.*.name' => ['required', 'string', 'max:255'],
        ];
    }
}
