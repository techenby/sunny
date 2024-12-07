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

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.users');
    }

    public function addStatusToList()
    {
        $this->form->status_list[] = ['emoji' => '🙂', 'status' => ''];
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
