<?php

use App\Livewire\Forms\Kiosk\RoutineForm;
use App\Models\Routine;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::kiosk-configure')] class extends Component
{
    public RoutineForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Routine::class);
    }

    /** @return Collection<int, Routine> */
    #[Computed]
    public function routines(): Collection
    {
        return Auth::user()->currentTeam
            ->routines()
            ->with('tasks')
            ->orderBy('name')
            ->get();
    }

    public function add(): void
    {
        $this->authorize('create', Routine::class);

        $this->form->resetForm();
        $this->modal('routine-form')->show();
    }

    public function edit(int $routineId): void
    {
        $routine = $this->findRoutine($routineId);

        $this->authorize('update', $routine);

        $this->form->load($routine);
        $this->modal('routine-form')->show();
    }

    public function addTask(): void
    {
        $this->form->addTask();
    }

    public function removeTask(int $index): void
    {
        $this->form->removeTask($index);
    }

    public function save(): void
    {
        $isEditing = $this->form->editingRoutine !== null;

        if ($this->form->editingRoutine) {
            $this->authorize('update', $this->form->editingRoutine);
        } else {
            $this->authorize('create', Routine::class);
        }

        $this->form->save();
        $this->modal('routine-form')->close();
        unset($this->routines);

        Flux::toast(
            variant: 'success',
            text: $isEditing ? __('Routine updated.') : __('Routine created.'),
        );
    }

    public function delete(int $routineId): void
    {
        $routine = $this->findRoutine($routineId);

        $this->authorize('delete', $routine);

        $routine->delete();
        unset($this->routines);

        Flux::toast(variant: 'success', text: __('Routine removed.'));
    }

    private function findRoutine(int $routineId): Routine
    {
        return Auth::user()->currentTeam
            ->routines()
            ->with('tasks')
            ->findOrFail($routineId);
    }
};
