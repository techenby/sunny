@props([
    'size' => 'md',
    'color' => null,
    'circle' => false,
    'src' => null,
    'alt' => null,
    'initials' => null,
    'name' => null,
    'ios' => null,
    'android' => null,
    'ref' => null,
    'class' => '',
])

@php
$initials ??= $name
    ? collect(preg_split('/\s+/', trim($name)))->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('')
    : null;

$box = match ($size) {
    '2xl' => 'h-14 w-14',
    'xl' => 'h-16 w-16',
    'lg' => 'h-12 w-12',
    'sm' => 'h-8 w-8',
    'xs' => 'h-6 w-6',
    default => 'h-10 w-10',
};

$radius = $circle ? 'rounded-full' : match ($size) {
    '2xl' => 'rounded',
    'xl' => 'rounded-xl',
    'sm' => 'rounded-md',
    'xs' => 'rounded-sm',
    default => 'rounded-lg',
};

$background = $color ? "bg-{$color}-200" : 'bg-theme-surface-variant';

$foreground = $color ? "text-{$color}-800" : 'text-theme-on-surface';

$iconSize = match ($size) {
    '2xl', 'lg' => 32,
    'sm' => 20,
    'xs' => 16,
    default => 24,
};

$initialsSize = match ($size) {
    '2xl', 'xl', 'lg' => 'text-base',
    'xs' => 'text-xs',
    default => 'text-sm',
};
@endphp

@if ($src)
    <image :ref="$ref" :src="$src" :alt="$alt ?? $name" class="{{ $box }} {{ $radius }} object-cover {{ $class }}" />
@else
    <column :ref="$ref" :a11y-label="$alt ?? $name" class="{{ $box }} {{ $radius }} {{ $background }} items-center justify-center {{ $class }}">
        @if ($ios || $android)
            <icon :ios="$ios" :android="$android" :size="$iconSize" class="{{ $foreground }}" />
        @elseif ($initials)
            <text font="medium" class="{{ $initialsSize }} {{ $foreground }}">{{ $initials }}</text>
        @endif
    </column>
@endif
