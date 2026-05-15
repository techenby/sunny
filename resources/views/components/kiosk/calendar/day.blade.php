<div class="flex min-h-0 divide-x divide-zinc-100 dark:divide-zinc-700">
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div
            wire:key="calendar-day-{{ $this->day['date']->toDateString() }}"
            @class([
                'border-b px-3 py-2 border-zinc-100 dark:border-zinc-700',
                'bg-zinc-50 dark:bg-zinc-800' => ! $this->day['is_today'],
                'bg-blue-100 dark:bg-blue-900' => $this->day['is_today'],
            ])
        >
            <div class="text-sm font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ $this->day['date']->format('l') }}</div>
            <div class="text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->day['date']->format('F j') }}</div>
        </div>

        @if (count($this->dayAllDayEvents) > 0)
            <div class="grid grid-cols-[4rem_1fr] border-b border-zinc-100 dark:border-zinc-700">
                <div class="border-r border-zinc-100 px-3 py-3 text-right text-xs font-medium uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ __('All day') }}
                </div>

                <div class="grid gap-2 p-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->dayAllDayEvents as $event)
                        <x-kiosk.calendar.event :$event />
                    @endforeach
                </div>
            </div>
        @endif

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div class="relative grid min-h-[120rem] grid-cols-[4rem_1fr]">
                @foreach (range(0, 23) as $hour)
                    <div
                        wire:key="calendar-day-hour-label-{{ $hour }}"
                        class="h-20 border-r border-zinc-100 px-3 pt-2 text-right text-xs font-medium text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                    >
                        {{ Carbon\CarbonImmutable::createFromTime($hour)->format('g A') }}
                    </div>

                    <div
                        wire:key="calendar-day-hour-row-{{ $hour }}"
                        class="h-20 border-b border-zinc-100 dark:border-zinc-800"
                    ></div>
                @endforeach

                <div class="pointer-events-none absolute inset-y-0 left-16 right-3">
                    @foreach ($this->dayTimedEvents as $event)
                        <div
                            wire:key="calendar-timed-event-{{ $event['feed_id'] }}-{{ $event['starts_at']->timestamp }}-{{ str($event['title'])->slug() }}"
                            @class([
                                'pointer-events-auto absolute overflow-hidden rounded-md border border-zinc-200 bg-white p-2 text-sm shadow-xs dark:border-zinc-700 dark:bg-zinc-950',
                                'line-through decoration-2 opacity-60' => ($event['response_status'] ?? null) === 'DECLINED',
                            ])
                            style="top: {{ $event['timeline_top'] }}%; height: {{ $event['timeline_height'] }}%; left: 0; right: 0; min-height: 2.5rem; border-left: 4px {{ ($event['response_status'] ?? null) === 'NEEDS-ACTION' ? 'dashed' : 'solid' }} {{ $event['feed_color'] }}"
                        >
                            <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                {{ $event['starts_at']->format('g:i A') }}

                                @if ($event['ends_at'])
                                    <span>{{ __('-') }}</span>
                                    <span>{{ $event['ends_at']->format('g:i A') }}</span>
                                @endif
                            </div>

                            <div class="truncate font-medium leading-snug text-zinc-900 dark:text-zinc-100">{{ $event['title'] }}</div>

                            @if ($event['location'])
                                <div class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $event['location'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="shrink-0">
        <flux:calendar
            static
            :value="$this->day['date']->toDateString()"
            size="xs"
            :navigation="false"
        />
    </div>
</div>
