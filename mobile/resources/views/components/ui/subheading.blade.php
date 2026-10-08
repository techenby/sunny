@props([
    'size' => 'base',
    'ref' => null,
    'maxLines' => null,
    'class' => '',
])

@php
$classes = collect([
    'text-theme-on-surface-variant',
    match ($size) {
        'xl' => 'text-xl',
        'lg' => 'text-lg',
        'sm' => 'text-sm',
        default => 'text-base',
    },
    $class,
])->filter()->implode(' ');
@endphp

<text :ref="$ref" :max-lines="$maxLines" class="{{ $classes }}">{{ $slot }}</text>
