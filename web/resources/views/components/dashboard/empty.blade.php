@props(['icon', 'heading', 'text', 'action' => null, 'href' => null, 'tile' => 'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300'])

<div {{ $attributes->class('flex flex-col items-center gap-1 px-4 py-6 text-center') }}>
    <div @class(['mb-2 flex size-10 items-center justify-center rounded-lg', $tile])>
        <flux:icon :name="$icon" class="size-5 opacity-75" />
    </div>

    <flux:heading level="3">{{ $heading }}</flux:heading>
    <flux:text class="max-w-70">{{ $text }}</flux:text>

    @if ($action || $slot->isNotEmpty())
        <div class="mt-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm">
            @if ($action)
                <flux:link :$href wire:navigate class="text-sm">{{ $action }}</flux:link>
            @endif

            {{ $slot }}
        </div>
    @endif
</div>
