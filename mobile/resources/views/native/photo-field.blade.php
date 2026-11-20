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
            <text class="text-sm font-semibold text-theme-primary-text">Remove photo</text>
        </pressable>
    @else
        <row class="w-full flex-wrap gap-3">
            <x-ui.button
                ref="{{ $refPrefix }}-photo-camera"
                variant="outline"
                class="flex-1"
                gap="2"
                :icon-size="20"
                :ios="Ios::Camera"
                :android="Android::PhotoCamera"
                a11y-label="Take photo"
                tap="takePhoto"
            >Take photo</x-ui.button>
            <x-ui.button
                ref="{{ $refPrefix }}-photo-library"
                variant="outline"
                class="flex-1"
                gap="2"
                :icon-size="20"
                :ios="Ios::Photo"
                :android="Android::Image"
                a11y-label="Choose photo"
                a11y-hint="Opens the photo library"
                tap="choosePhoto"
            >Choose photo</x-ui.button>
        </row>
    @endif
</column>
