<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function containerCount(): int
    {
        return Auth::user()->currentTeam->containers()->count();
    }

    #[Computed]
    public function itemCount(): int
    {
        return Auth::user()->currentTeam->items()->count();
    }

    #[Computed]
    public function unassignedItemCount(): int
    {
        return Auth::user()->currentTeam->items()->whereNull('container_id')->count();
    }
}; ?>

<section class="w-full">
    @include('pages.inventory.heading')

    <div class="grid gap-6 sm:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:subheading>{{ __('Containers') }}</flux:subheading>
            <flux:heading size="xl" class="mt-1">{{ $this->containerCount }}</flux:heading>
        </div>

        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:subheading>{{ __('Items') }}</flux:subheading>
            <flux:heading size="xl" class="mt-1">{{ $this->itemCount }}</flux:heading>
        </div>

        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:subheading>{{ __('Unassigned Items') }}</flux:subheading>
            <flux:heading size="xl" class="mt-1">{{ $this->unassignedItemCount }}</flux:heading>
        </div>
    </div>
</section>
