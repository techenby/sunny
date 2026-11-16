@props(['label' => __('More actions'), 'size' => 'sm'])

<flux:dropdown align="end">
    <flux:button variant="ghost" :$size icon="ellipsis-horizontal" class="text-zinc-500!" :aria-label="$label" />

    <flux:menu>
        {{ $slot }}
    </flux:menu>
</flux:dropdown>
