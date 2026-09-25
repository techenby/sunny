<div class="grid min-h-0 flex-1 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-700 md:grid-cols-7 md:divide-x md:divide-y-0">
    @foreach ($this->weekDays as $day)
        <div wire:key="calendar-day-{{ $day['date']->toDateString() }}" class="flex flex-col lg:min-h-0">
            <div @class([
                'border-b px-3 py-2 border-zinc-100 dark:border-zinc-700',
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
