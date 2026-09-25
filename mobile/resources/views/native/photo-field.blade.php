@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- Expects $photoPath, $refPrefix, and $alt; the including screen supplies
     takePhoto/choosePhoto/removePhoto via the CapturesPhoto trait. --}}

<column class="w-full gap-2">
    <text class="text-sm font-semibold text-theme-on-surface-variant">Photo</text>

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
        <row class="w-full items-center gap-3">
            <pressable
                ref="{{ $refPrefix }}-photo-camera"
                a11y-label="Take photo"
                class="h-12 w-12 items-center justify-center rounded-xl border border-theme-outline bg-theme-surface-variant"
                @tap="takePhoto"
            >
                <native:icon
                    :ios="Ios::Camera"
                    :android="Android::PhotoCamera"
                    :size="24"
                    class="text-theme-on-surface-variant"
                />
            </pressable>
            <pressable
                ref="{{ $refPrefix }}-photo-library"
                class="h-12 items-center justify-center px-2"
                a11y-hint="Opens the photo library"
                @tap="choosePhoto"
            >
                <text class="text-sm font-semibold text-theme-primary">View Library</text>
            </pressable>
        </row>
    @endif
</column>
