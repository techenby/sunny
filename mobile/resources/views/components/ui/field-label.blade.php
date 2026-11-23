@props([
    'ref' => null,
    'class' => '',
])

<text :ref="$ref" font="medium" class="text-sm text-theme-on-surface-variant {{ $class }}">{{ $slot }}</text>
