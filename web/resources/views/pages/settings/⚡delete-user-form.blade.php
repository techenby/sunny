<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    #[Computed]
    public function blockedMessage(): ?string
    {
        return Auth::user()->deletionBlockedMessage();
    }

    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        if ($this->blockedMessage) {
            $this->addError('password', $this->blockedMessage);

            return;
        }

        tap(Auth::user(), $logout(...))->purge();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Delete account') }}</flux:heading>
        <flux:subheading>{{ __('Delete your account, the teams only you use, and everything in them') }}</flux:subheading>
    </div>

    @if ($this->blockedMessage)
        <flux:callout variant="warning" icon="exclamation-triangle" data-test="delete-user-blocked">
            <flux:callout.text>{{ $this->blockedMessage }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('teams.index')" wire:navigate size="sm">{{ __('Manage teams') }}</flux:button>
            </x-slot>
        </flux:callout>
    @else
    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" data-test="delete-user-button">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>
    @endif

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading>
                    {{ __('This permanently deletes your account, the teams only you use, and the recipes, inventory, lists, routines and photos in them. In teams you share, your own lists and routines are deleted and everything else stays with the team. Enter your password to confirm.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" :label="__('Password')" type="password" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                    {{ __('Delete account') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
