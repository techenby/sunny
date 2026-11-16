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
                            <pressable
                                ref="{{ $refPrefix }}-photo-{{ $index }}-primary"
                                a11y-label="Make photo {{ $index + 1 }} the primary photo"
                                class="min-h-12 justify-center"
                                @tap="makePrimary({{ $index }})"
                            >
                                <row class="items-center gap-1">
                                    <icon :ios="Ios::StarFill" :android="Android::Star" :size="14" class="text-theme-primary-text" />
                                    <text font="semibold" class="text-sm text-theme-primary-text">Make primary</text>
                                </row>
                            </pressable>
                        @endif
                    </column>

                    <pressable
                        ref="{{ $refPrefix }}-photo-{{ $index }}-up"
                        a11y-label="Move photo {{ $index + 1 }} up"
                        class="h-12 w-12 items-center justify-center"
                        @tap="movePhotoUp({{ $index }})"
                    >
                        <icon :ios="Ios::ArrowUp" :android="Android::ArrowUpward" :size="18" class="{{ $index === 0 ? 'text-theme-on-surface-variant/40' : 'text-theme-on-surface' }}" />
                    </pressable>
                    <pressable
                        ref="{{ $refPrefix }}-photo-{{ $index }}-down"
                        a11y-label="Move photo {{ $index + 1 }} down"
                        class="h-12 w-12 items-center justify-center"
                        @tap="movePhotoDown({{ $index }})"
                    >
                        <icon :ios="Ios::ArrowDown" :android="Android::ArrowDownward" :size="18" class="{{ $loop->last ? 'text-theme-on-surface-variant/40' : 'text-theme-on-surface' }}" />
                    </pressable>
                    <pressable
                        ref="{{ $refPrefix }}-photo-{{ $index }}-remove"
                        a11y-label="Remove photo {{ $index + 1 }}"
                        class="h-12 w-12 items-center justify-center"
                        @tap="removePhoto({{ $index }})"
                    >
                        <icon :ios="Ios::Trash" :android="Android::Delete" :size="18" class="text-theme-on-surface-variant" />
                    </pressable>
                </row>
                @unless ($loop->last)
                    <divider class="ml-3" />
                @endunless
            @endforeach
        </column>
    @endif

    @if (count($photos) < ItemPhoto::MAX_PER_ITEM)
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
                a11y-label="Choose photos"
                a11y-hint="Opens the photo library"
                class="min-h-12 flex-1 items-center justify-center rounded-xl border border-theme-outline bg-theme-surface px-4"
                @tap="choosePhoto"
            >
                <row class="items-center gap-2">
                    <native:icon :ios="Ios::Photo" :android="Android::Image" :size="20" class="text-theme-on-surface" />
                    <text font="semibold" class="text-base text-theme-on-surface">Choose photo</text>
                </row>
            </pressable>
        </row>
    @else
        <x-ui.text size="sm" ref="{{ $refPrefix }}-photos-full">That’s the most photos one item can have. Remove one to add another.</x-ui.text>
    @endif
</column>
