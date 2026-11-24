@props([
    'size' => 'base',
    'ref' => null,
    'maxLines' => null,
    'class' => '',
])

@php
$classes = collect([
    'text-theme-on-surface',
    match ($size) {
        '2xl' => 'text-4xl',
        'xl' => 'text-2xl',
        'lg' => 'text-lg',
        default => 'text-base',
    },
    $class,
])->filter()->implode(' ');
@endphp

<text :ref="$ref" font="medium" :max-lines="$maxLines" class="{{ $classes }}">{{ $slot }}</text>
