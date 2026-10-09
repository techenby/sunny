@props(['name', 'icon', 'color', 'href' => null, 'household' => false])

<section {{ $attributes->class('space-y-2') }}>
    <div class="flex items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2">
            <flux:avatar size="xs" :$icon :$color icon:variant="outline" />
            <flux:link :$href wire:navigate variant="ghost" class="truncate">
                <flux:heading>{{ $name }}</flux:heading>
            </flux:link>
        </div>

        @if ($household)
            <flux:badge size="sm" color="zinc">{{ __('Household') }}</flux:badge>
        @endif
    </div>

    {{ $slot }}
</section>
