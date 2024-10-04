<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\rules;
use function Livewire\Volt\state;

state(['password' => '']);

rules(['password' => ['required', 'string', 'current_password']]);

$deleteUser = function (Logout $logout) {
    $this->validate();

    tap(Auth::user(), $logout(...))->delete();

    $this->redirect('/', navigate: true);
};

?>

<section class="space-y-6">
    <header>
        <flux:heading level="2" size="lg">{{ __('Delete Account') }}</flux:heading>
        <flux:subheading>{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}</flux:subheading>
    </header>

    <flux:button
        variant="danger"
        type="submit"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete Account') }}</flux:button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <flux:field class="mt-6">
                <flux:label class="sr-only">{{ __('Password') }}</flux:label>

                <flux:input wire:model="password" type="password" />

                <flux:error name="password" />
            </flux:field>

            <div class="mt-6 flex justify-end">
                <flux:button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="danger" type="submit" class="ms-3">
                    {{ __('Delete Account') }}
                </flux:button>
            </div>
        </form>
    </x-modal>
</section>
