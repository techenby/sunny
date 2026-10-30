@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- Expects $photoPath, $refPrefix, and $alt, and optionally $label and
     $labelClass; the including screen supplies takePhoto/choosePhoto/
     removePhoto via the CapturesPhoto trait. --}}

<column class="w-full gap-2">
    <text class="{{ $labelClass ?? 'text-sm font-semibold text-theme-on-surface-variant' }}">{{ $label ?? 'Photo' }}</text>

    @if ($photoPath)
        <image
            ref="{{ $refPrefix }}-photo"
            :src="$photoPath"
            :alt="$alt"
            class="w-full rounded-xl"
            :height="180"
            :fit="2"
        />
        <pressable
            ref="{{ $refPrefix }}-photo-remove"
            class="h-12 items-start justify-center self-start px-2"
            @tap="removePhoto"
        >
            <text class="text-sm font-semibold text-theme-primary">Remove photo</text>
        </pressable>
    @else
        <row class="w-full flex-wrap gap-3">
            <pressable
                ref="{{ $refPrefix }}-photo-camera"
                a11y-label="Take photo"
                class="min-h-12 flex-1 items-center justify-center rounded-xl border border-theme-outline bg-theme-surface px-4"
                @tap="takePhoto"
            >
                <row class="items-center gap-2">
                    <native:icon :ios="Ios::Camera" :android="Android::PhotoCamera" :size="20" class="text-theme-on-surface" />
                    <text font="semibold" class="text-base text-theme-on-surface">Take photo</text>
                </row>
            </pressable>
            <pressable
                ref="{{ $refPrefix }}-photo-library"
                a11y-label="Choose from photos"
                a11y-hint="Opens the photo library"
                class="min-h-12 flex-1 items-center justify-center rounded-xl border border-theme-outline bg-theme-surface px-4"
                @tap="choosePhoto"
            >
                <row class="items-center gap-2">
                    <native:icon :ios="Ios::Photo" :android="Android::Image" :size="20" class="text-theme-on-surface" />
                    <text font="semibold" class="text-base text-theme-on-surface">Choose from photos</text>
                </row>
            </pressable>
        </row>
    @endif
</column>
