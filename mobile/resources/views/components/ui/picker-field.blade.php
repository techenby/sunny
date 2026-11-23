@props([
    'label',
    'value',
    'tap',
    'opens' => 'menu',
    'ref' => null,
    'a11yLabel' => null,
    'a11yHint' => null,
])

@php
[$ios, $android] = match ($opens) {
    'date' => [\App\Icons\Ios::Calendar, \App\Icons\Android::CalendarMonth],
    default => [\App\Icons\Ios::ChevronUpChevronDown, \App\Icons\Android::UnfoldMore],
};
@endphp

<column class="w-full gap-1">
    <x-ui.field-label>{{ $label }}</x-ui.field-label>
    <pressable
        :ref="$ref"
        :a11y-label="$a11yLabel ?? $label.', '.$value"
        :a11y-hint="$a11yHint"
        :press-opacity="0.7"
        class="min-h-12 w-full justify-center rounded-lg border border-theme-outline px-3"
        @tap="{{ $tap }}"
    >
        <row class="w-full items-center gap-2">
            <text class="flex-1 text-[17] text-theme-on-surface">{{ $value }}</text>
            <icon :ios="$ios" :android="$android" :size="18" class="text-theme-on-surface-variant" />
        </row>
    </pressable>
</column>
