@props(['label' => __('Actions')])

<flux:dropdown align="end">
    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="text-zinc-500!" :aria-label="$label" />

    <flux:menu>
        {{ $slot }}
    </flux:menu>
</flux:dropdown>
