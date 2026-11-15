@props(['icon', 'src' => null, 'color' => null])

@if ($src)
    <span {{ $attributes->class('relative size-9 shrink-0 overflow-hidden rounded-md after:absolute after:inset-0 after:rounded-md after:inset-ring after:inset-ring-black/7 dark:after:inset-ring-white/10') }}>
        <img src="{{ $src }}" alt="" class="size-full object-cover" />
    </span>
@elseif ($color)
    <flux:avatar size="sm" :$icon :$color icon:variant="outline" :attributes="$attributes->class('size-9')" />
@else
    <span {{ $attributes->class('flex size-9 shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 text-zinc-400/60 dark:border-white/10 dark:bg-white/5 dark:text-zinc-500') }}>
        <flux:icon :name="$icon" class="size-4.5" />
    </span>
@endif
