<?php

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
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

        @if (! auth()->user()->hasVerifiedEmail())
            <flux:text class="mt-4">
                {{ __('Your email address is unverified.') }}
                <flux:link :href="route('verification.notice')">{{ __('Verify your email address') }}</flux:link>
            </flux:text>
        @endif

        <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
