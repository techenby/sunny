<x-app-layout>
    <x-slot name="header">
        <flux:heading size="xl" level="2">{{ __('Dashboard') }}</flux:heading>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <flux:card>
                <flux:heading>{{ __("You're logged in!") }}</flux:heading>
            </flux:card>
        </div>
    </div>
</x-app-layout>
