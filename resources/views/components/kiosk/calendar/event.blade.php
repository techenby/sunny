<div
    wire:key="calendar-event-{{ $event['feed_id'] }}-{{ $event['starts_at']->timestamp }}-{{ str($event['title'])->slug() }}"
    @class([
        'rounded-md border border-zinc-200 bg-white p-2 text-sm shadow-xs dark:border-zinc-700 dark:bg-zinc-950',
        'line-through decoration-2 opacity-60' => ($event['response_status'] ?? null) === 'DECLINED',
    ])
    style="border-left: 4px {{ ($event['response_status'] ?? null) === 'NEEDS-ACTION' ? 'dashed' : 'solid' }} {{ $event['feed_color'] }}"
>
    <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
        {{ $this->eventTimeRange($event) }}
    </div>

    <div class="wrap-break-word font-medium leading-snug text-zinc-900 dark:text-zinc-100">{{ $event['title'] }}</div>

    @if ($event['location'])
        <div class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $event['location'] }}</div>
    @endif
</div>
