<x-app-layout>
    <x-slot name="header">
        <flux:heading size="xl" level="2">{{ __('Profile') }}</flux:heading>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <flux:card>
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </flux:card>

            <flux:card>
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </flux:card>

            <flux:card>
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </flux:card>
        </div>
    </div>
</x-app-layout>
