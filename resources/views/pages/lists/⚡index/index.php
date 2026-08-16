<?php

use App\Livewire\Forms\Lists\ChecklistForm;
use App\Models\Checklist;
use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lists')] class extends Component
{
    public ChecklistForm $form;

    /** @return Collection<int, Checklist> */
    #[Computed]
    public function lists(): Collection
    {
        return $this->team()
            ->checklists()
            ->with('user')
            ->withCount([
                'items',
                'items as completed_items_count' => fn ($query) => $query->whereNotNull('completed_at'),
            ])
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function members(): Collection
    {
        return $this->team()->members()->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->form->reset();
        $this->form->user_id = $this->team()->soleMember()?->id;

        $this->modal('checklist-form')->show();
    }

    public function delete(Checklist $checklist): void
    {
        $this->authorize('delete', $checklist);

        $checklist->delete();

        Flux::toast(variant: 'success', text: __('List deleted.'));

        unset($this->lists);
    }

    public function edit(Checklist $checklist): void
    {
        $this->authorize('update', $checklist);

        $this->form->load($checklist);

        $this->modal('checklist-form')->show();
    }

    public function save(): void
    {
        if ($this->form->editingChecklist) {
            $this->authorize('update', $this->form->editingChecklist);
        } else {
            $this->authorize('create', Checklist::class);
        }

        $checklist = $this->form->save();

        $this->modal('checklist-form')->close();
        Flux::toast(variant: 'success', text: __('List saved.'));

        unset($this->lists);

        if ($checklist->items()->doesntExist()) {
            $this->redirect(route('lists.show', $checklist), navigate: true);
        }
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }
};
