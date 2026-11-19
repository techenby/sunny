<?php

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(UpdateUserProfileInformation $updateProfile): void
    {
        $user = Auth::user();

        $updateProfile->handle($user, $this->validate($this->profileRules($user->id)));

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return ! Auth::user()->hasVerifiedEmail();
    }
}; ?>

<section class="w-full">
    @include('pages.settings.heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        @if ($this->hasUnverifiedEmail)
            <form method="POST" action="{{ route('verification.send') }}" class="space-y-2">
                @csrf

                <flux:text>{{ __('Verify :email to use Sunny on the web.', ['email' => auth()->user()->email]) }}</flux:text>

                @if (session('status') === 'verification-link-sent')
                    <flux:text class="font-medium !dark:text-green-400 !text-green-600">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </flux:text>
                @endif

                <flux:button type="submit" size="sm">{{ __('Send verification email') }}</flux:button>
            </form>
        @endif

        <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
