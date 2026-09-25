<?php

use App\Livewire\Forms\Routines\RoutineForm;
use App\Models\Routine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Routines')] class extends Component
{
    public RoutineForm $form;

    /** @return Collection<int, User> */
    #[Computed]
    public function members(): Collection
    {
        return $this->team()->members()->orderBy('name')->get();
    }

    /** @return Collection<int, Routine> */
    #[Computed]
    public function routines(): Collection
    {
        return $this->team()
            ->routines()
            ->with('user')
            ->withCount('steps')
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->authorize('create', Routine::class);

        $this->form->reset();
        $this->form->starts_on = $this->team()->today()->toDateString();
        $this->form->user_id = $this->team()->soleMember()?->id;

        $this->modal('routine-form')->show();
    }

    public function delete(Routine $routine): void
    {
        $this->authorize('delete', $routine);

        $routine->delete();

        Flux::toast(variant: 'success', text: __('Routine deleted.'));

        unset($this->routines);
    }

    public function edit(Routine $routine): void
    {
        $this->authorize('update', $routine);

        $this->form->load($routine);

        $this->modal('routine-form')->show();
    }

    public function save(): void
    {
        if ($this->form->editingRoutine) {
            $this->authorize('update', $this->form->editingRoutine);
        } else {
            $this->authorize('create', Routine::class);
        }

        $routine = $this->form->save();

        $this->modal('routine-form')->close();
        Flux::toast(variant: 'success', text: __('Routine saved.'));

        unset($this->routines);

        if ($routine->steps()->doesntExist()) {
            $this->redirect(route('routines.show', $routine), navigate: true);
        }
    }

    public function toggleActive(Routine $routine): void
    {
        $this->authorize('update', $routine);

        $routine->update(['is_active' => ! $routine->is_active]);

        unset($this->routines);
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }
};
