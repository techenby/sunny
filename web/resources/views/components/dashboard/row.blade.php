@props(['href', 'heading', 'subheading' => null, 'meta' => null])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class('flex items-center gap-3 px-(--flux-bleed-x) py-3 hover:bg-zinc-900/2 dark:hover:bg-white/3') }}
>
    {{ $avatar }}

    <div class="min-w-0 flex-1">
        <flux:heading class="truncate">{{ $heading }}</flux:heading>
        @if ($subheading)
            <flux:text size="sm" class="truncate">{{ $subheading }}</flux:text>
        @endif
    </div>

    <flux:text size="sm" variant="subtle" class="shrink-0">{{ $meta }}</flux:text>
</a>
