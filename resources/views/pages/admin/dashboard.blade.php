<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component {
    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function with(): array
    {
        return [
            'userCount' => User::count(),
            'teamCount' => Team::count(),
        ];
    }
}; ?>

<section class="w-full">
    <x-slot:title>{{ __('Admin Dashboard') }}</x-slot:title>

    <div class="flex flex-col gap-6">
        <flux:heading size="xl">{{ __('Admin Dashboard') }}</flux:heading>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:card>
                <flux:heading size="lg">{{ __('Users') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Total registered users') }}</flux:text>
                <div class="mt-4 text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($userCount) }}</div>
            </flux:card>

            <flux:card>
                <flux:heading size="lg">{{ __('Teams') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Total teams') }}</flux:text>
                <div class="mt-4 text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($teamCount) }}</div>
            </flux:card>
        </div>
    </div>
</section>
