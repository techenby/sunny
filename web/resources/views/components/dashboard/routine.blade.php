@props(['name', 'icon', 'completed', 'total', 'household' => false])

<section {{ $attributes->class('space-y-2') }}>
    <div class="flex items-baseline justify-between gap-2">
        <div class="flex min-w-0 items-baseline gap-2">
            <flux:icon :name="$icon" class="size-4 shrink-0 translate-y-0.5 text-zinc-400" />
            <flux:heading class="truncate">{{ $name }}</flux:heading>
            @if ($household)
                <flux:badge size="sm" color="zinc">{{ __('Household') }}</flux:badge>
            @endif
        </div>

        <flux:text size="sm" class="tabular-nums">{{ $completed }}/{{ $total }}</flux:text>
    </div>

    <flux:progress :value="$completed" :max="max($total, 1)" class="h-1.5" />

    @if ($total > 0)
        <ul>
            {{ $slot }}
        </ul>
    @endif
</section>
