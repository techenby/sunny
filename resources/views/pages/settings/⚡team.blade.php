<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public string $name = '';

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user->ownsTeam($user->currentTeam)) {
            abort(403);
        }

        $this->name = $user->currentTeam->name;
    }

    public function updateTeamName(): void
    {
        $user = Auth::user();

        if (! $user->ownsTeam($user->currentTeam)) {
            abort(403);
        }

        $this->validate(['name' => ['required', 'string', 'max:255']]);

        $user->currentTeam->update(['name' => $this->name]);

        $this->dispatch('team-updated');
    }
}; ?>

<section class="w-full">
    @include('pages.settings.heading')

    <flux:heading class="sr-only">{{ __('Team Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Team')" :subheading="__('Update your team\'s name')">
        <form wire:submit="updateTeamName" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="off" />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="team-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
