@props([
    'ref' => null,
    'ios' => null,
    'android' => null,
    'tone' => 'info',
    'title' => '',
    'message' => '',
])

@php
[$fill, $iconColor, $titleColor] = match ($tone) {
    'error' => ['bg-theme-primary/15', 'text-theme-destructive', 'text-theme-destructive'],
    'offline' => ['bg-theme-surface-variant', 'text-theme-on-surface-variant', 'text-theme-on-surface'],
    default => ['bg-theme-primary/15', 'text-theme-primary', 'text-theme-on-surface'],
};
@endphp

<row :ref="$ref" class="w-full items-start gap-2.5 rounded-xl {{ $fill }} p-3.5">
    <icon :ios="$ios" :android="$android" :size="20" class="{{ $iconColor }}" />
    <column class="flex-1 gap-0.5">
        <text font="semibold" class="text-base {{ $titleColor }}">{{ $title }}</text>
        <text class="text-base text-theme-on-surface">{{ $message }}</text>
    </column>
</row>
