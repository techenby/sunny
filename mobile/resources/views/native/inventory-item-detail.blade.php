@use('App\Enums\ItemType')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\Inventory')
@use('App\Ui\Card')

@php($item = $this->item)

<native:top-bar :title="$item['name'] ?? 'Item'" display-mode="large" back />

@if ($item)
    <native:bottom-bar>
        <row class="w-full items-center gap-3 px-4 pb-2">
            <pressable
                ref="edit-item"
                a11y-label="Edit"
                class="min-h-12 flex-1 items-center justify-center rounded-full px-4 glass android:bg-theme-surface"
                @navigate('/inventory/'.$item['id'].'/edit')
            >
                <row class="items-center gap-2">
                    <native:icon :ios="Ios::Pencil" :android="Android::Edit" :size="18" class="text-theme-on-surface" />
                    <text font="semibold" class="text-lg text-theme-on-surface">Edit</text>
                </row>
            </pressable>
            <pressable
                ref="item-print-label"
                a11y-label="Print label"
                class="min-h-12 flex-1 items-center justify-center rounded-full px-4 glass android:bg-theme-surface"
                @tap="showQrCode"
            >
                <row class="items-center gap-2">
                    <native:icon :ios="Ios::Qrcode" :android="Android::QrCode2" :size="18" class="text-theme-on-surface" />
                    <text font="semibold" class="text-lg text-theme-on-surface">Print label</text>
                </row>
            </pressable>
        </row>
    </native:bottom-bar>

    <scroll-view ref="item-detail" fill class="bg-theme-background">
        <column class="w-full gap-7 px-4 pt-2 pb-8">
            <column class="w-full gap-3">
                <row class="w-full items-center gap-2">
                    <x-ui.avatar
                        size="xs"
                        :color="$item['type']->color()"
                        :ios="$item['type']->iosIcon()"
                        :android="$item['type']->androidIcon()"
                    />
                    <x-ui.text ref="item-summary" class="flex-1">
                        {{ $this->children ? $item['type']->label().' · '.Inventory::summary([...$item, 'children_count' => count($this->children)]) : $item['type']->label() }}
                    </x-ui.text>
                </row>

                @include('native.queued-change', ['refPrefix' => 'item', 'queuedChange' => $this->queuedChange])

                @if ($error !== '')
                    <text ref="item-error" class="text-base text-theme-destructive">{{ $error }}</text>
                @endif

                @php($photos = $this->photos)
                @if ($photos !== [])
                    @php($shown = $photos[$photoIndex] ?? $photos[0])
                    <column class="w-full">
                        <image
                            ref="item-photo"
                            :src="$shown['url']"
                            :alt="'Photo '.($shown['number']).' of '.$item['name']"
                            class="w-full h-[240] rounded-[18] object-cover"
                        />
                        @if (count($photos) > 1)
                            <row class="absolute top-[12] right-[12] rounded-full bg-black/60 px-2.5 py-1">
                                <text ref="item-photo-count" font="semibold" class="text-sm text-white">{{ $shown['number'] }} / {{ count($photos) }}</text>
                            </row>
                            @if ($shown['number'] === 1)
                                <row class="absolute bottom-[12] left-[12] items-center gap-1 rounded-full bg-black/60 px-2.5 py-1">
                                    <icon :ios="Ios::StarFill" :android="Android::Star" :size="12" class="text-white" />
                                    <text ref="item-photo-primary" font="semibold" class="text-sm text-white">Main photo</text>
                                </row>
                            @endif
                        @endif
                    </column>

                    @if (count($photos) > 1)
                        <scroll-view ref="item-photo-strip" horizontal :shows-indicators="false" class="w-full gap-2">
                            @foreach ($photos as $index => $photo)
                                <pressable
                                    ref="item-photo-thumb-{{ $index }}"
                                    a11y-label="Show photo {{ $photo['number'] }}{{ $index === $photoIndex ? ', showing' : '' }}"
                                    class="h-16 w-16 rounded-xl {{ $index === $photoIndex ? 'border-2 border-theme-primary' : '' }}"
                                    @tap="showPhoto({{ $index }})"
                                >
                                    <image :src="$photo['thumb']" :alt="'Photo '.$photo['number']" class="h-full w-full rounded-xl object-cover" />
                                </pressable>
                            @endforeach
                        </scroll-view>
                    @endif
                @endif
            </column>

            @if ($this->starterStorage)
                <column ref="item-empty-place" class="w-full gap-4">
                    <column class="w-full gap-2 px-1">
                        <x-ui.heading size="xl">Nothing stored here yet</x-ui.heading>
                        <x-ui.text size="lg">Storage is anything that holds things, like a box, shelf or drawer. Put Things in it, then search to see where anything lives.</x-ui.text>
                    </column>
                    <column class="w-full gap-2">
                        <x-ui.text class="px-1">Tap one to add it to {{ $item['name'] }}</x-ui.text>
                        <column class="{{ Card::classes() }} w-full">
                            @foreach ($this->starterStorage as $index => $name)
                                <pressable
                                    ref="item-starter-{{ $index }}"
                                    a11y-label="Add {{ $name }}"
                                    class="min-h-12 w-full justify-center px-4 py-2"
                                    @tap="addStarterStorage({{ $index }})"
                                >
                                    <row class="w-full items-center gap-4">
                                        <x-ui.avatar :color="ItemType::Bin->color()" :ios="ItemType::Bin->iosIcon()" :android="ItemType::Bin->androidIcon()" />
                                        <x-ui.heading size="lg" class="flex-1">{{ $name }}</x-ui.heading>
                                        <icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-primary-text" />
                                    </row>
                                </pressable>
                                @unless ($loop->last)
                                    <divider class="ml-[72]" />
                                @endunless
                            @endforeach
                        </column>
                    </column>
                    <column class="{{ Card::classes() }} w-full">
                        <pressable
                            ref="item-add"
                            a11y-label="Put something inside"
                            class="min-h-12 w-full justify-center px-4 py-2"
                            :menu="$this->addMenu()"
                        >
                            <row class="w-full items-center gap-4">
                                <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                                    <icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-primary-text" />
                                </column>
                                <text font="semibold" class="flex-1 text-lg text-theme-primary-text">Put something inside</text>
                            </row>
                        </pressable>
                    </column>
                </column>
            @endif

            <column class="w-full gap-3">
                <row class="w-full items-center gap-3 px-1">
                    <x-ui.heading size="lg" class="flex-1">Where it is</x-ui.heading>
                    <pressable
                        ref="item-move"
                        a11y-label="Move"
                        a11y-hint="Choose a new place for this"
                        class="min-h-12 items-center justify-center rounded-full bg-theme-surface-variant px-4"
                        @tap="startMove"
                    >
                        <row class="items-center gap-1.5">
                            <icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="14" class="text-theme-on-surface" />
                            <text font="semibold" class="text-base text-theme-on-surface">Move</text>
                        </row>
                    </pressable>
                </row>
                <column class="{{ Card::classes() }} w-full py-2">
                    @forelse ($this->path as $ancestor)
                        <pressable
                            ref="{{ $loop->last ? 'item-parent' : 'item-ancestor-'.$ancestor['id'] }}"
                            @tap="openAncestor({{ $ancestor['id'] }})"
                            :a11y-label="$ancestor['name']"
                            class="min-h-12 w-full justify-center px-4 py-1"
                        >
                            <row class="w-full items-center gap-4">
                                <x-ui.avatar
                                    :color="$ancestor['type']->color()"
                                    :ios="$ancestor['type']->iosIcon()"
                                    :android="$ancestor['type']->androidIcon()"
                                />
                                <x-ui.heading size="lg" class="flex-1">{{ $ancestor['name'] }}</x-ui.heading>
                                <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                            </row>
                        </pressable>
                        <column class="ml-[35] h-[10] w-[2] bg-theme-outline" />
                    @empty
                        @if ($item['type'] === ItemType::Location)
                            <row ref="item-home" class="w-full items-start gap-4 px-4 py-2">
                                <x-ui.avatar :ios="Ios::House" :android="Android::Home" />
                                <column class="flex-1 gap-0.5">
                                    <x-ui.heading size="lg">Home</x-ui.heading>
                                    <x-ui.text>One of the places in your home</x-ui.text>
                                </column>
                            </row>
                        @else
                            <row ref="item-unplaced" class="w-full items-center gap-4 px-4 py-2">
                                <x-ui.avatar :ios="Ios::House" :android="Android::Home" />
                                <column class="flex-1 gap-0.5">
                                    <x-ui.heading size="lg">Not in a place yet</x-ui.heading>
                                    <x-ui.text>Tap Move to put it away.</x-ui.text>
                                </column>
                            </row>
                        @endif
                        <column class="ml-[35] h-[10] w-[2] bg-theme-outline" />
                    @endforelse
                    <row ref="item-here" class="w-full items-center gap-4 px-4 py-2">
                        <column class="h-10 w-10 items-center justify-center rounded-full border-2 border-theme-primary">
                            <column class="h-3 w-3 rounded-full bg-theme-primary" />
                        </column>
                        <column class="flex-1 gap-0.5">
                            <text font="semibold" class="text-lg text-theme-on-surface">{{ $item['name'] }}</text>
                            <text font="semibold" class="text-base text-theme-primary-text">You’re here</text>
                        </column>
                    </row>
                </column>
            </column>

            @unless ($this->starterStorage)
            <column class="w-full gap-3">
                <x-ui.heading size="lg" class="px-1">{{ $this->children ? 'What’s inside ('.count($this->children).')' : 'What’s inside' }}</x-ui.heading>
                <column class="{{ Card::classes() }} w-full">
                    @foreach ($this->children as $child)
                        @include('native.inventory-item-row', ['item' => $child, 'ref' => 'item-child-'.$child['id'], 'from' => $item['id']])
                        <divider class="ml-[72]" />
                    @endforeach
                    <pressable
                        ref="item-add"
                        a11y-label="{{ $this->children ? 'Add something here' : 'Put something inside' }}"
                        class="min-h-12 w-full justify-center px-4 py-2"
                        :menu="$this->addMenu()"
                    >
                        <row class="w-full items-center gap-4">
                            <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                                <icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-primary-text" />
                            </column>
                            <text font="semibold" class="flex-1 text-lg text-theme-primary-text">{{ $this->children ? 'Add something here' : 'Put something inside' }}</text>
                        </row>
                    </pressable>
                </column>
            </column>
            @endunless

            @if ($item['metadata'])
                <column class="w-full gap-3">
                    <x-ui.heading size="lg" class="px-1">Details</x-ui.heading>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($item['metadata'] as $key => $value)
                            <row ref="metadata-{{ $key }}" class="w-full flex-wrap items-center gap-3 px-4 py-3">
                                <text class="flex-1 text-base text-theme-on-surface-variant">{{ ucfirst($key) }}</text>
                                <x-ui.heading>{{ $value }}</x-ui.heading>
                            </row>
                            @unless ($loop->last)
                                <divider class="ml-4" />
                            @endunless
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($this->photos === [])
                <pressable
                    ref="item-add-photo"
                    a11y-label="Add a photo"
                    class="{{ Card::classes('outline') }} min-h-12 w-full justify-center px-4 py-2"
                    @tap="editPhoto"
                >
                    <row class="w-full items-start gap-4">
                        <x-ui.avatar :ios="Ios::Camera" :android="Android::PhotoCamera" />
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="lg">Add a photo</x-ui.heading>
                            <x-ui.text>Helps you spot it on the shelf</x-ui.text>
                        </column>
                    </row>
                </pressable>
            @endif

            <pressable
                ref="delete-item"
                a11y-label="Delete {{ $item['name'] }}"
                class="min-h-12 w-full items-center justify-center"
                @tap="confirmDeleteItem"
            >
                <text font="semibold" class="text-center text-lg text-theme-destructive">Delete {{ $item['name'] }}</text>
            </pressable>
        </column>
    </scroll-view>

    @include('native.parent-picker-sheet', [
        'refPrefix' => 'move',
        'topLevelLabel' => $item['type'] === ItemType::Location ? 'Put it with the places in your home' : null,
        'selectedParent' => $this->selectedParent,
        'browsedParent' => $this->browsedParent,
        'parentPickerRows' => $this->parentPickerRows,
        'parentTrail' => $this->parentTrail,
    ])
@else
    <x-ui.not-found ref="item-missing" thing="item" />
@endif
