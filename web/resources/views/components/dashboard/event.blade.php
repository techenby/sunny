@props(['time', 'title', 'color', 'location' => null, 'declined' => false])

<div
    {{ $attributes->class([
        'flex gap-3 border-l-4 py-0.5 pl-3',
        'line-through opacity-60' => $declined,
    ]) }}
    style="border-color: {{ $color }}"
>
    <flux:text size="sm" class="w-28 shrink-0 tabular-nums">{{ $time }}</flux:text>

    <div class="min-w-0">
        <flux:text variant="strong" class="truncate">{{ $title }}</flux:text>
        @if ($location)
            <flux:text size="sm" class="truncate">{{ $location }}</flux:text>
        @endif
    </div>
</div>
