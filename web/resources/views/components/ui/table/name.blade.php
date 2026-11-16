@props(['href' => null])

@php
    $classes = 'flex min-w-0 items-center gap-3 rounded-md text-left font-medium text-zinc-800 group-hover/row:text-(--color-accent-content) focus-visible:text-(--color-accent-content) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-white';
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->class($classes) }}>
        {{ $thumb ?? '' }}

        <span class="truncate"><span>{!! trim($slot) !!}</span></span>
    </a>
@else
    <button type="button" {{ $attributes->class($classes) }}>
        {{ $thumb ?? '' }}

        <span class="truncate"><span>{!! trim($slot) !!}</span></span>
    </button>
@endif
