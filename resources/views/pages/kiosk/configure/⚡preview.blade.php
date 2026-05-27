<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

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

<div>
    <div class="flex items-end gap-4 mb-6">
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
        <flux:button :href="route('kiosk.calendar')" variant="primary" target="_blank" icon="arrow-top-right-on-square">Preview in New Tab</flux:button>
    </div>
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" style="width: {{ $width }}px; height: {{ $height }}px;">
        <iframe src="{{ route('kiosk.calendar') }}" frameborder="0" class="h-full w-full"></iframe>
    </div>
</div>
