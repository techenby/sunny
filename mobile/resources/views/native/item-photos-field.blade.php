@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Models\ItemPhoto')
@use('App\Ui\Card')

<column class="w-full gap-2">
    <column class="w-full gap-0.5">
        <text font="semibold" class="text-lg text-theme-on-surface">Photos (optional)</text>
        @if (count($photos) > 1)
            <x-ui.text size="sm">The first photo shows in lists. Use the arrows to change the order.</x-ui.text>
        @endif
    </column>

    @if ($photos !== [])
        <column ref="{{ $refPrefix }}-photos" class="{{ Card::classes() }} w-full">
            @foreach ($photos as $index => $photo)
                <row class="w-full items-center gap-2 py-2 pr-1 pl-3">
                    <image
                        ref="{{ $refPrefix }}-photo-{{ $index }}"
                        :src="$photo['src']"
                        :alt="'Photo '.($index + 1)"
                        class="h-16 w-16 rounded-xl object-cover"
                    />

                    <column class="flex-1 items-start">
                        @if ($index === 0)
                            <x-ui.badge :ref="$refPrefix.'-photo-primary-badge'" color="red">Primary</x-ui.badge>
                        @else
                            <x-ui.button
                                ref="{{ $refPrefix }}-photo-{{ $index }}-primary"
                                variant="plain"
                                size="sm"
                                :ios="Ios::StarFill"
                                :android="Android::Star"
                                a11y-label="Make photo {{ $index + 1 }} the primary photo"
                                tap="makePrimary({{ $index }})"
                            >Make primary</x-ui.button>
                        @endif
                    </column>

                    <x-ui.button
                        ref="{{ $refPrefix }}-photo-{{ $index }}-up"
                        variant="plain"
                        :ios="Ios::ArrowUp"
                        :android="Android::ArrowUpward"
                        :icon-size="18"
                        :icon-class="$index === 0 ? 'text-theme-on-surface-variant/40' : 'text-theme-on-surface'"
                        a11y-label="Move photo {{ $index + 1 }} up"
                        tap="movePhotoUp({{ $index }})"
                    />
                    <x-ui.button
                        ref="{{ $refPrefix }}-photo-{{ $index }}-down"
                        variant="plain"
                        :ios="Ios::ArrowDown"
                        :android="Android::ArrowDownward"
                        :icon-size="18"
                        :icon-class="$loop->last ? 'text-theme-on-surface-variant/40' : 'text-theme-on-surface'"
                        a11y-label="Move photo {{ $index + 1 }} down"
                        tap="movePhotoDown({{ $index }})"
                    />
                    <x-ui.button
                        ref="{{ $refPrefix }}-photo-{{ $index }}-remove"
                        variant="plain"
                        tone="muted"
                        :ios="Ios::Trash"
                        :android="Android::Delete"
                        :icon-size="18"
                        a11y-label="Remove photo {{ $index + 1 }}"
                        tap="removePhoto({{ $index }})"
                    />
                </row>
                @unless ($loop->last)
                    <divider class="ml-3" />
                @endunless
            @endforeach
        </column>
    @endif

    @if (count($photos) < ItemPhoto::MAX_PER_ITEM)
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
                a11y-label="Choose photos"
                a11y-hint="Opens the photo library"
                tap="choosePhoto"
            >Choose photo</x-ui.button>
        </row>
    @else
        <x-ui.text size="sm" ref="{{ $refPrefix }}-photos-full">That’s the most photos one item can have. Remove one to add another.</x-ui.text>
    @endif
</column>
