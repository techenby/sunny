<?php

use App\Enums\ItemType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function containerCount(): int
    {
        return Auth::user()->currentTeam->items()
            ->whereIn('type', [ItemType::Location, ItemType::Bin])
            ->count();
    }

    #[Computed]
    public function itemCount(): int
    {
        return Auth::user()->currentTeam->items()
            ->where('type', ItemType::Item)
            ->count();
    }

    #[Computed]
    public function unassignedItemCount(): int
    {
        return Auth::user()->currentTeam->items()
            ->where('type', ItemType::Item)
            ->whereNull('parent_id')
            ->count();
    }
}; ?>

<x:slot:breadcrumbs>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
        <flux:breadcrumbs.item :href="route('inventory.index')">Inventory</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Overview</flux:breadcrumbs.item>
    </flux:breadcrumbs>
</x:slot:breadcrumbs>

<section class="w-full">
    @include('pages.inventory.heading')

    <div class="grid gap-6 sm:grid-cols-3">
        <a href="{{ route('inventory.items') }}" wire:navigate>
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

        <a href="{{ route('inventory.items') }}" wire:navigate>
            <flux:card>
                <flux:subheading>{{ __('Unassigned Items') }}</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ $this->unassignedItemCount }}</flux:heading>
            </flux:card>
        </a>
    </div>
</section>
