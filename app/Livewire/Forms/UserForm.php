<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

class UserForm extends Form
{
    public $name = '';
    public $email = '';
    public $status = '';
    public $status_list = [];

    public $editingUser = null;

    public function setUser(User $user): void
    {
        $this->editingUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->status = $user->status;
        $this->status_list = $user->status_list ?? [['emoji' => '🙂', 'status' => '']];
    }

    public function update(): void
    {
        $validated = $this->validate();

        if ($validated['status'] === '') {
            $validated['status'] = null;
        }

        $this->editingUser->fill($validated);

        if ($this->editingUser->isDirty('email')) {
            $this->editingUser->email_verified_at = null;
        }

        $this->editingUser->save();

        $this->reset();
    }

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->editingUser->id)],
            'status' => ['nullable'],
            'status_list' => ['nullable', 'array'],
        ];
    }
}
