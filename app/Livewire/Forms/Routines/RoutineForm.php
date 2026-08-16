<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Routines;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\Routine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RoutineForm extends Form
{
    public ?Routine $editingRoutine = null;

    public string $name = '';

    public ?int $user_id = null;

    public string $time_of_day = TimeOfDay::Morning->value;

    public string $frequency = RoutineFrequency::Daily->value;

    /** @var array<int, int> */
    public array $weekdays = [];

    public ?int $day_of_month = null;

    public string $starts_on = '';

    public bool $is_active = true;

    public function load(Routine $routine): void
    {
        $this->fill([
            'editingRoutine' => $routine,
            'name' => $routine->name,
            'user_id' => $routine->user_id,
            'time_of_day' => $routine->time_of_day->value,
            'frequency' => $routine->frequency->value,
            'weekdays' => $routine->scheduledWeekdays(),
            'day_of_month' => $routine->day_of_month,
            'starts_on' => $routine->starts_on->toDateString(),
            'is_active' => $routine->is_active,
        ]);
    }

    public function save(): Routine
    {
        $team = Auth::user()->currentTeam;

        $this->starts_on = $this->starts_on ?: $team->today()->toDateString();

        $data = $this->validate();

        // Only persist the fields the chosen frequency actually reads, so a
        // routine switched from weekly to daily doesn't keep stale weekdays.
        $frequency = RoutineFrequency::from($data['frequency']);
        $data['weekdays'] = $frequency->usesWeekdays() ? array_values($data['weekdays']) : null;
        $data['day_of_month'] = $frequency->usesDayOfMonth() ? $data['day_of_month'] : null;

        if ($this->editingRoutine) {
            $this->editingRoutine->update($data);
            $routine = $this->editingRoutine;
        } else {
            $routine = $team->routines()->create($data);
        }

        $this->reset();

        return $routine;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $memberIds = Auth::user()->currentTeam->members()->pluck('users.id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::in($memberIds)],
            'time_of_day' => ['required', Rule::enum(TimeOfDay::class)],
            'frequency' => ['required', Rule::enum(RoutineFrequency::class)],
            'weekdays' => [
                Rule::requiredIf($this->frequency === RoutineFrequency::Weekly->value),
                'array',
            ],
            'weekdays.*' => ['integer', 'between:0,6'],
            'day_of_month' => [
                Rule::requiredIf($this->frequency === RoutineFrequency::Monthly->value),
                'nullable',
                'integer',
                'between:1,31',
            ],
            'starts_on' => ['required', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'user_id' => __('assignee'),
            'time_of_day' => __('time of day'),
            'day_of_month' => __('day of the month'),
            'starts_on' => __('start date'),
        ];
    }
}
