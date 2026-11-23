@props([
    'ref' => null,
    'class' => '',
])

<text :ref="$ref" class="text-sm text-theme-on-surface-variant {{ $class }}">{{ $slot }}</text>
