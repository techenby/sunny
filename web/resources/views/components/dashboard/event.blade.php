@props(['time', 'title', 'color', 'location' => null, 'declined' => false])

<div
    {{ $attributes->class([
        'flex min-w-0 flex-col border-l-4 py-0.5 pl-3',
        'line-through opacity-60' => $declined,
    ]) }}
    style="border-color: {{ $color }}"
>
    <flux:text size="sm" class="tabular-nums">{{ $time }}</flux:text>
    <flux:text variant="strong">{{ $title }}</flux:text>

    @if ($location)
        <flux:text size="sm" class="truncate">{{ $location }}</flux:text>
    @endif
</div>
