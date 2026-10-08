@props([
    'size' => 'base',
    'variant' => null,
    'color' => null,
    'ref' => null,
    'maxLines' => null,
    'class' => '',
])

@php
$classes = collect([
    match ($size) {
        'xl' => 'text-xl',
        'lg' => 'text-lg',
        'sm' => 'text-sm',
        default => 'text-base',
    },
    $color ? match ($color) {
        'amber', 'yellow', 'lime', 'green' => "text-{$color}-600 dark:text-{$color}-500",
        default => "text-{$color}-600 dark:text-{$color}-400",
    } : match ($variant) {
        'strong' => 'text-theme-on-surface',
        'subtle' => 'text-theme-on-surface-variant/70',
        default => 'text-theme-on-surface-variant',
    },
    $class,
])->filter()->implode(' ');
@endphp

<text :ref="$ref" :max-lines="$maxLines" class="{{ $classes }}">{{ $slot }}</text>
