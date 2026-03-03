@props(['type'])

@php
    $icon = match ($type) {
        App\Enums\ItemType::Location => 'map-pin',
        App\Enums\ItemType::Bin => 'folder',
        App\Enums\ItemType::Item => 'document',
    };

    $color = match ($type) {
        App\Enums\ItemType::Location => 'green',
        App\Enums\ItemType::Bin => 'blue',
        App\Enums\ItemType::Item => 'violet',
    };
@endphp

<flux:avatar size="xs" :icon=$icon icon:variant="outline" :color="$color" />
