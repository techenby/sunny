@use('App\Enums\CalendarEventStyle')

@props(['event', 'eventStyle' => null, 'compact' => false])

@php
    $eventStyle ??= $this->team->calendar_event_style;
    $needsAction = ($event['response_status'] ?? null) === 'NEEDS-ACTION';
    $color = $event['feed_color'] instanceof BackedEnum ? $event['feed_color']->value : $event['feed_color'];

    $look = $eventStyle === CalendarEventStyle::Filled && $needsAction ? 'outlined' : $eventStyle->value;
@endphp

<div
    data-event-style="{{ $look }}"
    {{ $attributes->class([
        'border border-zinc-200 bg-white text-zinc-900 shadow-xs [--event-muted:var(--color-zinc-500)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:[--event-muted:var(--color-zinc-400)]' => $look === 'stripe',
        'border border-(--event-color)/25 bg-[color-mix(in_oklab,var(--event-color)_14%,white)] text-zinc-900 [--event-muted:var(--color-zinc-600)] dark:bg-[color-mix(in_oklab,var(--event-color)_30%,var(--color-zinc-950))] dark:text-zinc-100 dark:[--event-muted:var(--color-zinc-300)]' => $look === 'tinted',
        'border border-(--event-color) bg-(--event-color) text-white shadow-xs [--event-muted:rgb(255_255_255/0.85)]' => $look === 'filled',
        'border-2 border-dashed border-(--event-color) bg-white text-zinc-900 [--event-muted:var(--color-zinc-500)] dark:bg-zinc-950 dark:text-zinc-100 dark:[--event-muted:var(--color-zinc-400)]' => $look === 'outlined',
        'border-l-(--event-color)' => in_array($look, ['stripe', 'tinted']),
        $compact ? 'border-l-3' : 'border-l-4' => in_array($look, ['stripe', 'tinted']),
        '[border-left-style:dashed]' => $needsAction && in_array($look, ['stripe', 'tinted']),
        'line-through decoration-2 opacity-60' => ($event['response_status'] ?? null) === 'DECLINED',
    ])->merge(['style' => "--event-color: {$color}"]) }}
>
    {{ $slot }}
</div>
