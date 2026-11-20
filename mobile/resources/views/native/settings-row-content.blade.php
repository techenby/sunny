<row class="w-full items-center gap-4">
    @if ($monogram)
        <column class="h-10 w-10 items-center justify-center rounded-full bg-theme-primary">
            <text font="medium" class="text-base text-theme-on-primary">{{ $monogram }}</text>
        </column>
    @elseif ($ios || $android)
        <column class="h-10 w-10 items-center justify-center">
            <icon :ios="$ios" :android="$android" :size="22" class="{{ $iconColor }}" />
        </column>
    @endif
    <column class="flex-1 gap-0.5">
        <text font="medium" class="text-lg {{ $color }}">{{ $headline }}</text>
        @if ($supporting)
            <x-ui.text>{{ $supporting }}</x-ui.text>
        @endif
    </column>
    @if ($toggle)
        <toggle ref="{{ $ref }}-toggle" :value="$toggle['value']" :a11y-label="$headline" @change="{{ $toggle['change'] }}" />
    @elseif ($trailingText)
        <text ref="{{ $ref }}-trailing" font="semibold" class="text-base {{ $disabled ? 'text-theme-on-surface-variant' : 'text-theme-primary-text' }}">{{ $trailingText }}</text>
    @elseif ($trailingIcon)
        <icon ref="{{ $ref }}-trailing" :ios="$trailingIcon[0]" :android="$trailingIcon[1]" :size="$trailingIcon[2]" class="{{ $trailingIcon[3] }}" />
    @endif
</row>
