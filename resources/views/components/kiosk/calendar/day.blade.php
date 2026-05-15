<div class="flex divide-x h-full divide-zinc-100 dark:divide-zinc-700">
    <div class="flex-1 flex min-h-0 flex-col overflow-hidden">
        <div
            wire:key="calendar-day-{{ $this->day['date']->toDateString() }}"
            @class([
                'border-b px-5 py-4 border-zinc-100 dark:border-zinc-700',
                'bg-zinc-50 dark:bg-zinc-800' => ! $this->day['is_today'],
                'bg-blue-100 dark:bg-blue-900' => $this->day['is_today'],
            ])
        >
            <div class="text-sm font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ $this->day['date']->format('l') }}</div>
            <div class="text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->day['date']->format('F j') }}</div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
            @if (count($this->day['events']) > 0)
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->day['events'] as $event)
                        <x-kiosk.calendar.event :$event />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="shrink-none">
        <flux:calendar
            static
            value="2026-05-15"
            size="xs"
            :navigation="false"
        />
    </div>
</div>
