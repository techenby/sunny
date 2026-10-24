@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Inventory" display-mode="large" :back="false" />

@include('native.search-bottom-bar', [
    'refPrefix' => 'inventory',
    'placeholder' => 'Find something',
    'search' => $search,
])

<scroll-view ref="inventory-list" fill class="bg-theme-background">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        @if ($search !== '')
            @if ($this->searchResults)
                <column class="{{ Card::classes() }} w-full">
                    @foreach ($this->searchResults as $result)
                        @include('native.inventory-item-row', [
                            'item' => $result,
                            'ref' => 'inventory-result-'.$result['id'],
                            'from' => null,
                            'supporting' => $result['where'],
                        ])
                        @unless ($loop->last)
                            <divider class="ml-[72]" />
                        @endunless
                    @endforeach
                </column>
            @else
                <column ref="inventory-no-matches" class="w-full items-center gap-2 px-4 py-8">
                    <icon :ios="Ios::Magnifyingglass" :android="Android::SearchOff" :size="28" class="text-theme-on-surface-variant" />
                    <x-ui.heading size="lg">Nothing called “{{ $search }}”</x-ui.heading>
                    <x-ui.text>Check the spelling, or try a shorter word.</x-ui.text>
                </column>
            @endif
        @else
            <row class="w-full items-center gap-3">
                <pressable
                    ref="inventory-add"
                    a11y-label="Add"
                    class="min-h-12 flex-1 items-center justify-center rounded-full bg-theme-primary px-4"
                    :menu="$this->addMenu()"
                >
                    <row class="items-center gap-2">
                        <icon :ios="Ios::Plus" :android="Android::Add" :size="16" class="text-theme-on-primary" />
                        <text font="semibold" class="text-base text-theme-on-primary">Add</text>
                    </row>
                </pressable>
                <pressable
                    ref="inventory-scan-label"
                    a11y-label="Scan label"
                    class="min-h-12 flex-1 items-center justify-center rounded-full bg-theme-surface-variant px-4"
                    @tap="scanCode"
                >
                    <row class="items-center gap-2">
                        <icon :ios="Ios::QrcodeViewfinder" :android="Android::QrCodeScanner" :size="16" class="text-theme-on-surface" />
                        <text font="semibold" class="text-base text-theme-on-surface">Scan label</text>
                    </row>
                </pressable>
            </row>

            @if ($this->places)
                <column class="w-full gap-2">
                    <x-ui.subheading class="px-1">Places in your home</x-ui.subheading>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->places as $item)
                            @include('native.inventory-item-row', ['item' => $item, 'ref' => 'inventory-item-'.$item['id'], 'from' => null])
                            @unless ($loop->last)
                                <divider class="ml-[72]" />
                            @endunless
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($this->unplaced)
                <column class="w-full gap-2">
                    <column class="w-full gap-0.5 px-1">
                        <x-ui.subheading>Not in a place yet</x-ui.subheading>
                        <x-ui.text size="sm">Open one and tap Move to put it away.</x-ui.text>
                    </column>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->unplaced as $item)
                            @include('native.inventory-item-row', ['item' => $item, 'ref' => 'inventory-item-'.$item['id'], 'from' => null])
                            @unless ($loop->last)
                                <divider class="ml-[72]" />
                            @endunless
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($this->items === [])
                <column ref="inventory-empty" class="w-full items-center gap-2 px-4 py-8">
                    <icon :ios="Ios::Archivebox" :android="Android::Inventory2" :size="28" class="text-theme-on-surface-variant" />
                    <x-ui.heading size="lg">Nothing here yet</x-ui.heading>
                    <x-ui.text>Tap Add to start with a room, like Garage. If you've added things on the website, pull down on the Summary tab to bring them in.</x-ui.text>
                </column>
            @endif
        @endif
    </column>
</scroll-view>
