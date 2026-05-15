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

            <flux:button type="button" variant="ghost" size="sm" icon="chevron-left" wire:click="previousWeek" />
            <flux:button type="button" variant="filled" size="sm" wire:click="currentWeek">{{ __('Today') }}</flux:button>
            <flux:button type="button" variant="ghost" size="sm" icon="chevron-right" wire:click="nextWeek" />
        </div>
    </div>

    <div class="grid min-h-0 flex-1 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-700 lg:grid-cols-7 lg:divide-x lg:divide-y-0">
        @foreach ($this->weekDays as $day)
            <div wire:key="calendar-day-{{ $day['date']->toDateString() }}" class="flex min-h-40 flex-col lg:min-h-0">
                <div @class([
                    'border-b px-3 py-3 border-zinc-100 dark:border-zinc-700',
                    'bg-zinc-50 dark:bg-zinc-800' => ! $day['is_today'],
                    'bg-blue-100 dark:bg-blue-900' => $day['is_today'],
                ])>
                    <div>
                        <div class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ $day['date']->format('D') }}</div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $day['date']->format('M j') }}</div>
                    </div>
                </div>

                <div class="flex flex-1 flex-col gap-2 p-3">
                    @foreach ($day['events'] as $event)
                        <x-kiosk.calendar.event :$event />
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
