@props([
    'color' => null,
    'size' => null,
    'rounded' => false,
    'ref' => null,
    'class' => '',
])

@php
$classes = collect([
    match ($size) {
        'lg' => 'text-sm py-1.5',
        'sm' => 'text-xs py-1',
        default => 'text-sm py-1',
    },
    $rounded ? 'rounded-full px-3' : 'rounded-md px-2',
    match ($color) {
        null, 'zinc' => 'text-theme-on-surface bg-theme-on-surface-variant/15 dark:bg-theme-on-surface-variant/40',
        'red', 'orange' => "text-{$color}-700 dark:text-{$color}-200 bg-{$color}-400/20 dark:bg-{$color}-400/40",
        'amber', 'yellow' => "text-{$color}-800 dark:text-{$color}-200 bg-{$color}-400/25 dark:bg-{$color}-400/40",
        'lime' => 'text-lime-800 dark:text-lime-200 bg-lime-400/25 dark:bg-lime-400/40',
        'green', 'emerald', 'teal', 'cyan', 'sky', 'blue' => "text-{$color}-800 dark:text-{$color}-200 bg-{$color}-400/20 dark:bg-{$color}-400/40",
        default => "text-{$color}-700 dark:text-{$color}-200 bg-{$color}-400/20 dark:bg-{$color}-400/40",
    },
    $class,
])->filter()->implode(' ');
@endphp

<text :ref="$ref" font="medium" class="{{ $classes }}">{{ $slot }}</text>
