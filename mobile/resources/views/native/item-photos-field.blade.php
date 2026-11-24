@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Models\ItemPhoto')
@use('App\Ui\Card')

<column class="w-full gap-1">
    <x-ui.field-label>Photos (optional)</x-ui.field-label>

    @if ($photos !== [])
        <column ref="{{ $refPrefix }}-photos" class="{{ Card::classes() }} w-full">
            @foreach ($photos as $index => $photo)
                <x-ui.row
                    :ref="$refPrefix.'-photo-row-'.$index"
                    :photo="$photo['src']"
                    :photo-ref="$refPrefix.'-photo-'.$index"
                    :alt="'Photo '.($index + 1)"
                    :a11y-label="'Photo '.($index + 1).($index === 0 ? ', main photo' : '')"
                    :badge="$index === 0 ? 'Main photo' : null"
                    :menu="$photoMenus[$index]"
                    :menu-ref="$refPrefix.'-photo-'.$index.'-more'"
                    :menu-label="'More for photo '.($index + 1)"
                    :last="$loop->last"
                />
            @endforeach
        </column>
    @endif

    @if (count($photos) > 1)
        <x-ui.field-help>The main photo shows in lists.</x-ui.field-help>
    @endif

    @if (count($photos) < ItemPhoto::MAX_PER_ITEM)
        <row class="w-full flex-wrap gap-3">
            <x-ui.button
                ref="{{ $refPrefix }}-photo-camera"
                variant="secondary"
                class="flex-1"
                :ios="Ios::Camera"
                :android="Android::PhotoCamera"
                a11y-label="Take photo"
                tap="takePhoto"
            >Take photo</x-ui.button>
            <x-ui.button
                ref="{{ $refPrefix }}-photo-library"
                variant="secondary"
                class="flex-1"
                :ios="Ios::Photo"
                :android="Android::Image"
                a11y-label="Choose photos"
                a11y-hint="Opens the photo library"
                tap="choosePhoto"
            >Choose photo</x-ui.button>
        </row>
    @else
        <x-ui.text size="sm" ref="{{ $refPrefix }}-photos-full">That’s the most photos it can have. Remove one to add another.</x-ui.text>
    @endif
</column>
