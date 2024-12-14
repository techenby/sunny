<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Forms\UserForm;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use WithDataTable;
    use WithPagination;

    public UserForm $form;

    public $apiToken;

    public $status = ['emoji' => '🙂', 'text' => ''];
    public $setStatusFor;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.users');
    }

    public function addStatusToList()
    {
        $this->form->status_list[] = ['emoji' => '🙂', 'status' => ''];
    }

    public function clearStatus($id)
    {
        User::find($id)->update(['status' => null]);
        unset($this->users);
    }

    public function closeApiToken()
    {
        $this->modal('api-token')->close();
        $this->reset(['apiToken']);
    }

    public function delete($id)
    {
        User::find($id)->delete();
        unset($this->users);
    }

    public function edit($id)
    {
        $user = User::find($id);
        $this->form->setUser($user);
        $this->modal('edit-user')->show();
    }

    public function getToken($id)
    {
        $user = User::find($id);
        $this->apiToken = $user->createToken('Sunny')->plainTextToken;

        $this->modal('api-token')->show();
    }

    public function setStatus()
    {
        $this->setStatusFor->update([
            'status' => is_string($this->status) ? $this->status : $this->status['emoji'] . ' - ' . $this->status['text'],
        ]);
        $this->reset(['status', 'setStatusFor']);
        $this->modal('set-status')->close();
    }

    public function showStatusModal($id)
    {
        $this->setStatusFor = User::find($id);
        $this->modal('set-status')->show();
    }

    public function removeStatusFromList($index)
    {
        unset($this->form->status_list[$index]);
        $this->form->status_list = array_values($this->form->status_list);
    }

    public function save()
    {
        $this->form->update();
        $this->modal('edit-user')->close();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }
}
