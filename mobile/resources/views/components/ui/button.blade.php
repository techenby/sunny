@props([
    'variant' => 'primary',
    'ios' => null,
    'android' => null,
    'iconTrailing' => false,
    'maxLines' => null,
    'tap' => null,
    'navigate' => null,
    'menu' => null,
    'disabled' => false,
    'loading' => false,
    'ref' => null,
    'a11yLabel' => null,
    'a11yHint' => null,
    'class' => '',
])

@php
$iconOnly = $slot->isEmpty();
$inactive = $disabled || $loading;
$pressOpacity = 0.7;

if ($variant === 'destructive') {
    [$ios, $android, $iconTrailing] = [\App\Icons\Ios::Trash, \App\Icons\Android::Delete, false];
}

$hasIcon = $ios || $android;

$surface = match (true) {
    $variant === 'text' => null,
    $variant === 'glass' => 'glass android:bg-theme-surface',
    $inactive => 'bg-theme-surface-variant',
    $variant === 'secondary' => 'bg-theme-surface-variant',
    $variant === 'destructive' => 'bg-theme-destructive',
    default => 'bg-theme-primary',
};

$color = match (true) {
    $inactive => 'text-theme-on-surface/40',
    $variant === 'text' => 'text-theme-secondary',
    $variant === 'secondary', $variant === 'glass' => 'text-theme-on-surface',
    $variant === 'destructive' => 'text-theme-on-destructive',
    default => 'text-theme-on-primary',
};

$classes = collect([
    $iconOnly ? 'h-12 w-12 items-center justify-center' : 'min-h-12 justify-center',
    $surface ? 'rounded-full' : null,
    $surface && ! $iconOnly ? 'items-center px-4' : null,
    $surface,
    $class,
])->filter()->implode(' ');

if ($inactive) {
    $a11yLabel = ($a11yLabel ?? trim(html_entity_decode(strip_tags($slot->toHtml()), ENT_QUOTES))).', unavailable';
}
@endphp

@if ($inactive)
    <column :ref="$ref" :a11y-label="$a11yLabel" :class="$classes">
@elseif ($navigate)
    <pressable :ref="$ref" :a11y-label="$a11yLabel" :a11y-hint="$a11yHint" :class="$classes" :press-opacity="$pressOpacity" @navigate($navigate)>
@else
    <pressable :ref="$ref" :a11y-label="$a11yLabel" :a11y-hint="$a11yHint" :class="$classes" :press-opacity="$pressOpacity" :menu="$menu" @tap="{{ $tap }}">
@endif
    @if ($iconOnly)
        <icon :ios="$ios" :android="$android" :size="$surface ? 22 : 18" :class="$color" />
    @elseif ($hasIcon || $loading)
        <row class="items-center gap-2">
            @if ($loading)
                <activity-indicator size="small" />
            @elseif (! $iconTrailing)
                <icon :ios="$ios" :android="$android" :size="18" :class="$color" />
            @endif
            <text font="semibold" :max-lines="$maxLines" class="text-center text-base {{ $color }}">{{ $slot }}</text>
            @if ($iconTrailing && ! $loading)
                <icon :ios="$ios" :android="$android" :size="18" :class="$color" />
            @endif
        </row>
    @else
        <text font="semibold" :max-lines="$maxLines" class="text-center text-base {{ $color }}">{{ $slot }}</text>
    @endif
@if ($inactive)
    </column>
@else
    </pressable>
@endif
