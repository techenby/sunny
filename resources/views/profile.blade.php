<x-app-layout>
    <flux:main class="space-y-6">
        <flux:heading size="xl" level="2">{{ __('Profile') }}</flux:heading>

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
    </flux:main>
</x-app-layout>
