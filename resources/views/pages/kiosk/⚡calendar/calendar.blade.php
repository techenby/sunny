<div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="lg">{{ __('Week') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->weekLabel }}</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button type="button" variant="ghost" size="sm" icon="chevron-left" wire:click="previousWeek" />
            <flux:button type="button" variant="filled" size="sm" wire:click="currentWeek">{{ __('Today') }}</flux:button>
            <flux:button type="button" variant="ghost" size="sm" icon="chevron-right" wire:click="nextWeek" />
        </div>
    </div>

    @if ($this->feeds->isEmpty())
        <div class="px-5 py-12 text-center">
            <flux:icon name="calendar-days" class="mx-auto mb-3 size-8 text-zinc-400" />
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Add a calendar feed to see weekly events.') }}
            </flux:text>
        </div>
    @else
        <div class="grid min-h-[32rem] divide-y divide-zinc-100 dark:divide-zinc-700 lg:grid-cols-7 lg:divide-x lg:divide-y-0">
            @foreach ($this->weekDays as $day)
                <div wire:key="calendar-day-{{ $day['date']->toDateString() }}" class="flex min-h-40 flex-col">
                    <div @class([
                        'border-b px-3 py-3 border-zinc-100 dark:border-zinc-700',
                        'bg-zinc-50 dark:bg-zinc-800' => ! $day['is_today'],
                        'bg-blue-100 dark:bg-blue-900' => $day['is_today'],
                    ])>
                        <div>
                            <div @class([
                                'text-xs font-medium uppercase',
                                'text-zinc-500 dark:text-zinc-400'
                            ])>{{ $day['date']->format('D') }}</div>
                            <div @class([
                                'text-sm font-semibold',
                                'text-zinc-900 dark:text-zinc-100'
                            ])>{{ $day['date']->format('M j') }}</div>
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col gap-2 p-3">
                        @forelse ($day['events'] as $event)
                            <div
                                wire:key="calendar-event-{{ $event['feed_id'] }}-{{ $event['starts_at']->timestamp }}-{{ str($event['title'])->slug() }}"
                                class="rounded-md border border-zinc-200 bg-white p-2 text-sm shadow-xs dark:border-zinc-700 dark:bg-zinc-950"
                                style="border-left: 4px solid {{ $event['feed_color'] }}"
                            >
                                <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                    <span class="size-2 rounded-full" style="background-color: {{ $event['feed_color'] }}"></span>
                                    {{ $event['all_day'] ? __('All day') : $event['starts_at']->format('g:i A') }}
                                </div>

                                <div class="break-words font-medium leading-snug text-zinc-900 dark:text-zinc-100">{{ $event['title'] }}</div>
                                <div class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $event['feed_name'] }}</div>

                                @if ($event['location'])
                                    <div class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $event['location'] }}</div>
                                @endif
                            </div>
                        @empty
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
