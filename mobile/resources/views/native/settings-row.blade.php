@use('App\Icons\Android')
@use('App\Icons\Ios')

@php
    $supporting ??= null;
    $ios ??= null;
    $android ??= null;
    $monogram ??= null;
    $tone ??= null;
    $trailing ??= null;
    $trailingText ??= null;
    $toggle ??= null;
    $tap ??= null;
    $url ??= null;
    $hint ??= null;
    $disabled ??= false;
    $last ??= false;

    $color = match ($tone) {
        'primary' => 'text-theme-primary-text',
        'destructive' => 'text-theme-destructive',
        default => 'text-theme-on-surface',
    };
    $iconColor = $tone ? $color : 'text-theme-on-surface-variant';
    $hasLeading = $monogram || $ios || $android;
    $label = implode(', ', array_filter([$headline, $supporting, $trailingText, $trailing === 'check' ? 'selected' : null]));
    $rowClass = 'min-h-12 w-full justify-center px-4 py-2'.($disabled ? ' opacity-50' : '');
    $trailingIcon = match ($trailing) {
        'chevron' => [Ios::ChevronRight, Android::ChevronRight, 14, 'text-theme-on-surface-variant'],
        'external' => [Ios::ArrowUpRight, Android::OpenInNew, 16, 'text-theme-on-surface-variant'],
        'refresh' => [Ios::ArrowClockwise, Android::Refresh, 16, 'text-theme-primary-text'],
        'check' => [Ios::Checkmark, Android::Check, 18, 'text-theme-primary-text'],
        default => null,
    };
@endphp

@if ($toggle)
    <column ref="{{ $ref }}" class="{{ $rowClass }}">
        @include('native.settings-row-content')
    </column>
@elseif ($disabled || (! $tap && ! $url))
    <column ref="{{ $ref }}" :a11y-label="$label" class="{{ $rowClass }}">
        @include('native.settings-row-content')
    </column>
@elseif ($url)
    <pressable ref="{{ $ref }}" :a11y-label="$label" :a11y-hint="$hint" class="{{ $rowClass }}" @navigate($url)>
        @include('native.settings-row-content')
    </pressable>
@else
    <pressable ref="{{ $ref }}" :a11y-label="$label" :a11y-hint="$hint" class="{{ $rowClass }}" @tap="{{ $tap }}">
        @include('native.settings-row-content')
    </pressable>
@endif
@unless ($last)
    <divider class="{{ $hasLeading ? 'ml-[72]' : 'ml-4' }}" />
@endunless
