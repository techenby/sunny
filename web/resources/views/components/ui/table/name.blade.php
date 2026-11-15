@props(['href'])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class('flex min-w-0 items-center gap-3 rounded-md font-medium text-zinc-800 group-hover/row:text-(--color-accent-content) focus-visible:text-(--color-accent-content) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-white') }}
>
    {{ $thumb ?? '' }}

    <span class="truncate">{{ $slot }}</span>
</a>
