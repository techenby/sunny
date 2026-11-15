<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::kiosk-configure')] class extends Component
{
    public int $width = 1020;
    public int $height = 600;

    public function swap()
    {
        $width = $this->width;
        $height = $this->height;

        $this->width = $height;
        $this->height = $width;
    }
};
?>

<div
    x-data="{ available: 0, get scale() { return this.available ? Math.min(1, this.available / $wire.width) : 1 } }"
    x-resize="available = $width"
>
    <div class="mb-6 flex flex-wrap items-end gap-4">
        <flux:field>
            <flux:label>{{ __('Width') }}</flux:label>
            <flux:input.group>
                <flux:input wire:model.live.debounce.250ms="width" step="5" type="number" />
                <flux:input.group.suffix>px</flux:input.group.suffix>
            </flux:input.group>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Height') }}</flux:label>
            <flux:input.group>
                <flux:input wire:model.live.debounce.250ms="height" step="5" type="number" />
                <flux:input.group.suffix>px</flux:input.group.suffix>
            </flux:input.group>
        </flux:field>
        <flux:button wire:click="swap" icon="arrow-path-rounded-square" square />
        <flux:button :href="route('kiosk.calendar')" variant="primary" target="_blank" icon="arrow-top-right-on-square">{{ __('Open preview') }}</flux:button>
    </div>

    <flux:badge size="sm" class="mb-3" x-text="`{{ __('Shown at') }} ${Math.round(scale * 100)}%`">{{ __('Shown at') }} 100%</flux:badge>

    <div class="overflow-hidden" :style="`width: ${$wire.width * scale}px; height: ${$wire.height * scale}px`" style="width: {{ $width }}px; height: {{ $height }}px;">
        <div
            class="origin-top-left overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
            :style="`width: ${$wire.width}px; height: ${$wire.height}px; transform: scale(${scale})`"
            style="width: {{ $width }}px; height: {{ $height }}px;"
        >
            <iframe src="{{ route('kiosk.calendar') }}" frameborder="0" class="h-full w-full"></iframe>
        </div>
    </div>
</div>
