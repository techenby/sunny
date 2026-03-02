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
        <a href="{{ route('inventory.containers') }}" wire:navigate>
            <flux:card>
                <flux:subheading>{{ __('Containers') }}</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ $this->containerCount }}</flux:heading>
            </flux:card>
        </a>

        <a href="{{ route('inventory.items') }}" wire:navigate>
            <flux:card>
                <flux:subheading>{{ __('Items') }}</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ $this->itemCount }}</flux:heading>
            </flux:card>
        </a>

        <a href="{{ route('inventory.items', ['unassigned' => true]) }}" wire:navigate>
            <flux:card>
                <flux:subheading>{{ __('Unassigned Items') }}</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ $this->unassignedItemCount }}</flux:heading>
            </flux:card>
        </a>
    </div>
</section>
