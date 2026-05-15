<div class="flex min-h-0 flex-1 flex-col overflow-hidden">
    <div class="w-full shrink-0 bg-zinc-50 px-3 pt-2 text-sm font-semibold dark:bg-zinc-800">
        {{ now()->format('F') }}
    </div>
    <div class="grid min-h-0 flex-1 grid-rows-[auto_1fr] overflow-hidden">
        <div class="grid shrink-0 grid-cols-7 border-b border-zinc-100 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
            @foreach (range(0, 6) as $offset)
                <div wire:key="calendar-month-weekday-{{ $offset }}" class="px-3 pb-2 text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">
                    {{ $this->monthDays[$offset]['date']->format('D') }}
                </div>
            @endforeach
        </div>

        <div class="grid min-h-0 grid-cols-7 auto-rows-[7rem] divide-x divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-700">
            @foreach ($this->monthDays as $day)
                <div
                    wire:key="calendar-day-{{ $day['date']->toDateString() }}"
                    @class([
                        'flex min-h-0 flex-col gap-1 overflow-hidden p-2',
                        'bg-white dark:bg-zinc-950' => $day['is_current_month'] && ! $day['is_today'],
                        'bg-zinc-50 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-500' => ! $day['is_current_month'] && ! $day['is_today'],
                        'bg-blue-100 dark:bg-blue-900' => $day['is_today'],
                    ])
                >
                    <div @class([
                        'text-sm font-semibold',
                        'text-zinc-900 dark:text-zinc-100' => $day['is_current_month'] || $day['is_today'],
                        'text-zinc-400 dark:text-zinc-500' => ! $day['is_current_month'] && ! $day['is_today'],
                    ])>
                        {{ $day['date']->format('j') }}
                    </div>

                    <div class="flex min-h-0 flex-col gap-1 overflow-hidden">
                        @foreach (array_slice($day['events'], 0, 2) as $event)
                            <div
                                wire:key="calendar-month-event-{{ $event['feed_id'] }}-{{ $event['starts_at']->timestamp }}-{{ str($event['title'])->slug() }}"
                                @class([
                                    'truncate rounded-sm border border-zinc-200 bg-white px-1.5 py-1 text-xs leading-tight text-zinc-900 shadow-xs dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100',
                                    'line-through decoration-2 opacity-60' => ($event['response_status'] ?? null) === 'DECLINED',
                                ])
                                style="border-left: 3px {{ ($event['response_status'] ?? null) === 'NEEDS-ACTION' ? 'dashed' : 'solid' }} {{ $event['feed_color'] }}"
                            >
                                <span class="font-medium">{{ $event['all_day'] ? __('All day') : $event['starts_at']->format('g:i A') }}</span>
                                {{ $event['title'] }}
                            </div>
                        @endforeach

                        @if (count($day['events']) > 2)
                            <div class="px-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                {{ __('+:count events', ['count' => count($day['events']) - 2]) }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
