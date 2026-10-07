<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    #[Computed]
    public function greeting(): string
    {
        $hour = CarbonImmutable::now(Auth::user()->currentTeam->timezone)->hour;
        $name = Auth::user()->name;

        return match (true) {
            $hour < 12 => __('Good morning, :name', ['name' => $name]),
            $hour < 17 => __('Good afternoon, :name', ['name' => $name]),
            default => __('Good evening, :name', ['name' => $name]),
        };
    }
};
?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ $this->greeting }}</flux:heading>
        <flux:text class="mt-1">{{ auth()->user()->currentTeam->today()->format('l, F j') }}</flux:text>
    </div>

    <livewire:dashboard.events lazy />

    <div class="grid gap-6 lg:grid-cols-2">
        <livewire:dashboard.routines />
        <livewire:dashboard.lists />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <livewire:dashboard.recipes />
        <livewire:dashboard.items />
    </div>
</div>
