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

<section>
    <header class="mb-6">
        <flux:heading level="2" size="lg">{{ __('Delete Account') }}</flux:heading>
        <flux:subheading>{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}</flux:subheading>
    </header>

    <flux:modal.trigger name="delete-account">
        <flux:button variant="danger" >{{ __('Delete Account') }}</flux:button>
    </flux:modal.trigger>

    <flux:modal name="delete-account" class="md:w-96">
        <form wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">{{ __('Are you sure you want to delete your account?') }}</flux:heading>
                <flux:subheading>{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}</flux:subheading>
            </div>

            <flux:field class="mt-6">
                <flux:label class="sr-only">{{ __('Password') }}</flux:label>

                <flux:input wire:model="password" type="password" />

                <flux:error name="password" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" class="ms-3">{{ __('Delete Account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
