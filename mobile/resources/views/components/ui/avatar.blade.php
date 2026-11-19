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

$boxSize = match ($size) {
    '2xl' => 14,
    'xl' => 16,
    'lg' => 12,
    'sm' => 8,
    'xs' => 6,
    default => 10,
};

$box = $src || $ios || $android || ! $initials ? "h-{$boxSize} w-{$boxSize}" : "min-h-{$boxSize} min-w-{$boxSize}";

$radius = $circle ? 'rounded-full' : match ($size) {
    '2xl' => 'rounded',
    'xl' => 'rounded-xl',
    'sm' => 'rounded-md',
    'xs' => 'rounded-sm',
    default => 'rounded-lg',
};

$background = $color ? "bg-{$color}-200 dark:bg-{$color}-400/25" : 'bg-theme-surface-variant';

$foreground = $color ? "text-{$color}-800 dark:text-{$color}-300" : 'text-theme-on-surface';

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
