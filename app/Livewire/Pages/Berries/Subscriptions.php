<?php

namespace App\Livewire\Pages\Berries;

use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Forms\SubscriptionForm;
use App\Models\Subscription;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Subscriptions extends Component
{
    use WithDataTable;
    use WithPagination;

    public SubscriptionForm $form;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.berries.subscriptions');
    }

    #[Computed]
    public function subscriptions(): LengthAwarePaginator
    {
        return Subscription::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('type', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function create()
    {
        $this->form->reset();
        $this->modal('subscription-form')->show();
    }

    public function delete(int $id)
    {
        Subscription::find($id)->delete();
        unset($this->subscriptions);
    }

    public function edit(int $id): void
    {
        $this->form->set(Subscription::find($id));
        $this->modal('subscription-form')->show();
    }

    public function save()
    {
        if (isset($this->form->subscription)) {
            $this->form->update();
        } else {
            $this->form->store();
        }

        unset($this->subscriptions);
        $this->modal('subscription-form')->close();
    }
}
