<div class="flex h-dvh flex-col overflow-hidden" wire:poll.600s>
    <div class="flex shrink-0 flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.clock :timezone="auth()->user()->currentTeam->timezone"/>

        <div class="flex items-center gap-2">
            @if ($this->failedFeeds->isNotEmpty())
            <flux:dropdown>
                <flux:button icon="exclamation-triangle" variant="primary" color="yellow"/>

                <flux:popover>
                    <flux:heading>
                        {{ __("Couldn't load :feeds.", ['feeds' => $this->failedFeeds->pluck('name')->join(', ', __(' and '))]) }}
                    </flux:heading>
                    <flux:text>
                        {{ __('Open Sunny on your phone or computer and go to Configure → Calendar to fix it.') }}
                    </flux:text>
                </flux:popover>
            </flux:dropdown>
            @endif
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

            <flux:button.group>
                <flux:button icon="chevron-left" wire:click="previous" />
                <flux:button wire:click="current">{{ __('Today') }}</flux:button>
                <flux:button icon="chevron-right" wire:click="next" />
            </flux:button.group>
        </div>
    </div>

    <x-dynamic-component :component="'kiosk.calendar.' . $format" />
</div>
