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
        /** @var Routine $routine */
        $routine = $this->route('routine');

        $frequency = $this->input('frequency', $routine->frequency->value);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::in($this->route('team')->members()->pluck('users.id'))],
            'time_of_day' => ['sometimes', 'required', Rule::enum(TimeOfDay::class)],
            'frequency' => ['sometimes', 'required', Rule::enum(RoutineFrequency::class)],
            'weekdays' => [
                Rule::requiredIf($frequency === RoutineFrequency::Weekly->value
                    && ($this->has('weekdays') || $routine->scheduledWeekdays() === [])),
                'nullable',
                'array',
            ],
            'weekdays.*' => ['integer', 'between:0,6'],
            'day_of_month' => [
                Rule::requiredIf($frequency === RoutineFrequency::Monthly->value
                    && ($this->has('day_of_month') || $routine->day_of_month === null)),
                'nullable',
                'integer',
                'between:1,31',
            ],
            'starts_on' => ['sometimes', 'required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['sometimes', 'array', 'list'],
            'steps.*' => ['array'],
            'steps.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('routine_steps', 'id')->where('routine_id', $routine->id)->whereNull('deleted_at'),
            ],
            'steps.*.name' => ['required', 'string', 'max:255'],
        ];
    }
}
