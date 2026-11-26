<x-kiosk.calendar.event-surface
    :$event
    wire:key="calendar-event-{{ $event['feed_id'] }}-{{ $event['starts_at']->timestamp }}-{{ str($event['title'])->slug() }}"
    class="rounded-md p-2 text-sm"
>
    <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-(--event-muted)">
        {{ $this->eventTimeRange($event) }}
    </div>

    <div class="wrap-break-word font-medium leading-snug">{{ $event['title'] }}</div>

    @if ($event['location'])
        <div class="mt-1 truncate text-xs text-(--event-muted)">{{ $event['location'] }}</div>
    @endif
</x-kiosk.calendar.event-surface>
