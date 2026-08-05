<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Kiosk;

use App\Actions\Kiosk\CreateRoutine;
use App\Actions\Kiosk\UpdateRoutine;
use App\Enums\RoutineCadence;
use App\Models\Routine;
use App\Models\RoutineTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RoutineForm extends Form
{
    public ?Routine $editingRoutine = null;

    public string $name = '';

    public string $cadence = RoutineCadence::Daily->value;

    public ?int $reset_weekday = null;

    /** @var array<int, array{key: string, id: ?int, name: string}> */
    public array $tasks = [];

    public function load(Routine $routine): void
    {
        $this->resetForm();

        $this->fill([
            'editingRoutine' => $routine,
            'name' => $routine->name,
            'cadence' => $routine->cadence->value,
            'reset_weekday' => $routine->reset_weekday,
            'tasks' => $routine->tasks
                ->map(fn (RoutineTask $task): array => [
                    'key' => "routine-task-{$task->id}",
                    'id' => $task->id,
                    'name' => $task->name,
                ])
                ->values()
                ->all(),
        ]);

        if ($this->tasks === []) {
            $this->addTask();
        }
    }

    public function resetForm(): void
    {
        $this->editingRoutine = null;
        $this->name = '';
        $this->cadence = RoutineCadence::Daily->value;
        $this->reset_weekday = null;
        $this->tasks = [];
        $this->addTask();
        $this->resetValidation();
    }

    public function addTask(): void
    {
        $this->tasks[] = [
            'key' => 'new-routine-task-' . Str::uuid(),
            'id' => null,
            'name' => '',
        ];
    }

    public function removeTask(int $index): void
    {
        if (! isset($this->tasks[$index]) || count($this->tasks) === 1) {
            return;
        }

        unset($this->tasks[$index]);
        $this->tasks = array_values($this->tasks);
    }

    public function save(): Routine
    {
        $data = $this->validate();
        $data['reset_weekday'] = $data['cadence'] === RoutineCadence::Daily->value
            ? null
            : $data['reset_weekday'];

        $routine = $this->editingRoutine
            ? (new UpdateRoutine)->handle($this->editingRoutine, $data)
            : (new CreateRoutine)->handle(Auth::user()->currentTeam, $data);

        $this->resetForm();

        return $routine;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $routineId = $this->editingRoutine?->id ?? 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'cadence' => ['required', Rule::enum(RoutineCadence::class)],
            'reset_weekday' => [
                'nullable',
                Rule::requiredIf($this->cadence === RoutineCadence::Weekly->value),
                'integer',
                'between:0,6',
            ],
            'tasks' => ['required', 'array', 'min:1', 'max:25'],
            'tasks.*.key' => ['required', 'string', 'max:64'],
            'tasks.*.id' => [
                'nullable',
                'integer',
                Rule::prohibitedIf($this->editingRoutine === null),
                Rule::exists(RoutineTask::class, 'id')->where('routine_id', $routineId),
            ],
            'tasks.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'reset_weekday' => __('reset day'),
            'tasks.*.name' => __('task name'),
        ];
    }
}
