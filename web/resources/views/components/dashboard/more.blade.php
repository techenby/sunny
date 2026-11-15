@props(['href', 'label', 'note' => null])

<div {{ $attributes->class('flex items-center justify-between gap-4 border-t border-zinc-900/5 pt-4 dark:border-white/10') }}>
    <flux:link :$href wire:navigate class="text-sm">{{ $label }}</flux:link>

    @if ($note)
        <flux:text size="sm" variant="subtle">{{ $note }}</flux:text>
    @endif
</div>
