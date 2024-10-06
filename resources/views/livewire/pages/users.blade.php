<?php

use App\Models\User;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{computed, layout, state, usesPagination};

layout('layouts.app');
usesPagination();

state([
    'sortBy',
    'sortDirection' => 'desc',
    'editingUser',
    'name' => '',
    'email' => '',
]);

$edit = function ($id) {
    $user = $this->users->firstWhere('id', $id);

    $this->editingUser = $user;
    $this->name = $user->name;
    $this->email = $user->email;

    $this->modal('edit-user')->show();
};

$save = function () {
    $validated = $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->editingUser->id)],
    ]);

    $this->editingUser->fill($validated);

    if ($this->editingUser->isDirty('email')) {
        $this->editingUser->email_verified_at = null;
    }

    $this->editingUser->save();

    $this->reset('editingUser', 'name', 'email');
    $this->modal('edit-user')->close();
};

$sort = function ($column) {
    if ($this->sortBy === $column && $this->sortDirection === 'asc') {
        $this->reset('sortBy', 'sortDirection');
    } else if ($this->sortBy === $column) {
        $this->sortDirection = 'asc';
    } else {
        $this->sortBy = $column;
        $this->sortDirection = 'desc';
    }
};

$users = computed(function () {
    return User::query()
        ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
        ->paginate(10);
})

?>

<flux:main class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>

    <flux:table :paginate="$this->users">
        <flux:columns>
            <flux:column>ID</flux:column>
            <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:column>
            <flux:column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">Email</flux:column>
        </flux:columns>

        <flux:rows>
            @foreach ($this->users as $user)
                <flux:row :key="$user->id">
                    <flux:cell>{{ $user->id }}</flux:cell>
                    <flux:cell>{{ $user->name }}</flux:cell>
                    <flux:cell>{{ $user->email }}</flux:cell>

                    <flux:cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $user->id }})">Edit</flux:menu.item>
                                <flux:menu.item variant="danger" icon="trash">Delete</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:cell>
                </flux:row>
            @endforeach
        </flux:rows>
    </flux:table>

    <flux:modal name="edit-user" variant="flyout">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Update User</flux:heading>
                <flux:subheading>Make changes to a user's details.</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />
            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:main>
