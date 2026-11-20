@props([
    'variant' => 'primary',
    'tone' => null,
    'size' => 'auto',
    'font' => 'semibold',
    'ios' => null,
    'android' => null,
    'iconTrailing' => false,
    'iconSize' => null,
    'iconClass' => null,
    'gap' => null,
    'flush' => false,
    'labelClass' => '',
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
$native = in_array($variant, ['primary', 'secondary', 'destructive', 'ghost'], true);
$font = $font ?: null;

if ($native) {
    $size = $size === 'auto' ? ($variant === 'ghost' ? null : 'lg') : ($size ?: null);
} else {
    $iconOnly = $slot->isEmpty();
    $hasIcon = $ios || $android;

    $surface = match ($variant) {
        'filled' => 'rounded-full bg-theme-primary',
        'tint' => 'rounded-full bg-theme-primary/15',
        'neutral' => 'rounded-full bg-theme-surface-variant',
        'glass' => 'rounded-full glass android:bg-theme-surface',
        'outline' => 'rounded-xl border border-theme-outline bg-theme-surface',
        'outline-pill' => 'rounded-full border border-theme-outline bg-theme-surface',
        default => null,
    };

    $color = match ($tone ?? match ($variant) {
        'filled' => 'on-primary',
        'tint', 'plain' => 'accent',
        default => 'neutral',
    }) {
        'on-primary' => 'text-theme-on-primary',
        'accent' => 'text-theme-primary-text',
        'destructive' => 'text-theme-destructive',
        'muted' => 'text-theme-on-surface-variant',
        default => 'text-theme-on-surface',
    };

    [$textSize, $sizeIcon, $sizeGap] = match ($size) {
        'lg' => ['text-lg', 18, '2'],
        'sm' => ['text-sm', 14, '1'],
        default => ['text-base', 16, '1.5'],
    };

    $iconSize ??= $iconOnly ? 22 : $sizeIcon;
    $iconClass ??= $color;
    $gap ??= $sizeGap;

    $classes = collect([
        $iconOnly ? 'h-12 w-12 items-center justify-center' : 'min-h-12 justify-center',
        $iconOnly || ! $surface ? null : 'items-center',
        $iconOnly || ! $surface || $flush ? null : 'px-4',
        $surface,
        $class,
    ])->filter()->implode(' ');
}
@endphp

@if ($native && $navigate)
    <button :ref="$ref" :variant="$variant" :size="$size" :font="$font" :class="$class" :ios-icon="$ios" :android-icon="$android" :a11y-label="$a11yLabel" :a11y-hint="$a11yHint" :disabled="$disabled" :loading="$loading" @navigate($navigate)>{{ $slot }}</button>
@elseif ($native)
    <button :ref="$ref" :variant="$variant" :size="$size" :font="$font" :class="$class" :ios-icon="$ios" :android-icon="$android" :a11y-label="$a11yLabel" :a11y-hint="$a11yHint" :disabled="$disabled" :loading="$loading" :menu="$menu" @tap="{{ $tap }}">{{ $slot }}</button>
@else
    @if ($navigate)
        <pressable :ref="$ref" :a11y-label="$a11yLabel" :a11y-hint="$a11yHint" :class="$classes" @navigate($navigate)>
    @else
        <pressable :ref="$ref" :a11y-label="$a11yLabel" :a11y-hint="$a11yHint" :class="$classes" :menu="$menu" @tap="{{ $tap }}">
    @endif
        @if ($iconOnly)
            <icon :ios="$ios" :android="$android" :size="$iconSize" :class="$iconClass" />
        @elseif ($hasIcon)
            <row class="items-center gap-{{ $gap }}">
                @unless ($iconTrailing)
                    <icon :ios="$ios" :android="$android" :size="$iconSize" :class="$iconClass" />
                @endunless
                <text :font="$font" :max-lines="$maxLines" class="{{ $textSize }} {{ $color }} {{ $labelClass }}">{{ $slot }}</text>
                @if ($iconTrailing)
                    <icon :ios="$ios" :android="$android" :size="$iconSize" :class="$iconClass" />
                @endif
            </row>
        @else
            <text :font="$font" :max-lines="$maxLines" class="{{ $textSize }} {{ $color }} {{ $labelClass }}">{{ $slot }}</text>
        @endif
    </pressable>
@endif
