@props(['label'])

<section {{ $attributes->class('min-w-0 space-y-2') }}>
    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $label }}</flux:heading>

    {{ $slot }}
</section>
