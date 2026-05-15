<div class="flex h-dvh flex-col overflow-hidden">
    <div class="flex shrink-0 flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $this->nowLabel }}</flux:heading>
        </div>

        <div class="flex items-center gap-2">
            <flux:dropdown>
                <flux:button icon="funnel" icon:variant="outline" />
                <flux:menu keep-open>
                    <flux:menu.checkbox.group wire:model.live="selectedFeeds">
                        @foreach ($this->feeds as $feed)
                            <flux:menu.checkbox :value="$feed->id">
                                <span class="size-2 rounded-full mr-2" style="background: {{ $feed->color }}"></span>
                                {{ $feed->name }}
                            </flux:menu.checkbox>
                        @endforeach
                    </flux:menu.checkbox.group>
                </flux:menu>
            </flux:dropdown>

            <flux:radio.group wire:model.live="format" variant="segmented">
                <flux:radio value="day" label="Day" />
                <flux:radio value="week" label="Week" />
                <flux:radio value="month" label="Month" />
            </flux:radio.group>

            <flux:button type="button" variant="ghost" size="sm" icon="chevron-left" wire:click="previous" />
            <flux:button type="button" variant="filled" size="sm" wire:click="current">{{ __('Today') }}</flux:button>
            <flux:button type="button" variant="ghost" size="sm" icon="chevron-right" wire:click="next" />
        </div>
    </div>

    <x-dynamic-component :component="'kiosk.calendar.' . $format" />
</div>
