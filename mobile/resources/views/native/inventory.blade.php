@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Inventory" display-mode="large" :back="false" />

{{-- Search with Scan inside it and the add button beside it. --}}
<native:bottom-bar>
    <row class="w-full items-center gap-3 px-4 pb-2">
        <row class="min-h-12 flex-1 items-center gap-2 rounded-full pr-1 pl-4 glass android:bg-theme-surface">
            <native:icon :ios="Ios::Magnifyingglass" :android="Android::Search" :size="18" class="text-theme-on-surface-variant" />
            <bare-text-input
                ref="inventory-search"
                class="flex-1"
                placeholder="Search"
                a11y-label="Search"
                :value="$search"
                sync-mode="debounce"
                debounce-ms="300"
                @change="updateSearch"
            />
            <x-ui.button
                ref="inventory-scan-label"
                variant="text"
                :ios="Ios::QrcodeViewfinder"
                :android="Android::QrCodeScanner"
                a11y-label="Scan label"
                tap="scanCode"
            />
        </row>
        <x-ui.button ref="inventory-add" variant="glass" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add" :menu="$this->addMenu()" />
    </row>
</native:bottom-bar>

<scroll-view ref="inventory-list" fill class="bg-theme-background">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        @if ($search !== '')
            @if ($this->searchResults)
                <column class="{{ Card::classes() }} w-full">
                    @foreach ($this->searchResults as $result)
                        @include('native.inventory-item-row', ['item' => $result, 'ref' => 'inventory-result-'.$result['id'], 'from' => null, 'supporting' => $result['where'], 'last' => $loop->last])
                    @endforeach
                </column>
            @else
                <x-ui.empty-state
                    ref="inventory-no-matches"
                    :ios="Ios::Magnifyingglass"
                    :android="Android::SearchOff"
                    :heading="'Nothing called “'.$search.'”'"
                    text="Check the spelling, or try a shorter word."
                />
            @endif
        @else
            @if ($this->places)
                <column class="w-full gap-2">
                    <x-ui.subheading class="px-1">Places in your home</x-ui.subheading>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->places as $item)
                            @include('native.inventory-item-row', ['item' => $item, 'ref' => 'inventory-item-'.$item['id'], 'from' => null, 'last' => $loop->last])
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($this->unplaced)
                <column class="w-full gap-2">
                    <x-ui.subheading class="px-1">Not in a place yet</x-ui.subheading>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->unplaced as $item)
                            @include('native.inventory-item-row', ['item' => $item, 'ref' => 'inventory-item-'.$item['id'], 'from' => null, 'last' => $loop->last])
                        @endforeach
                    </column>
                    <x-ui.text size="sm" class="px-1">Open one and tap Move to put it away.</x-ui.text>
                </column>
            @endif

            @if ($this->items === [])
                <x-ui.empty-state
                    ref="inventory-empty"
                    :ios="Ios::Archivebox"
                    :android="Android::Inventory2"
                    heading="Nothing here yet"
                    text="Tap + to start with a room, like Garage. If you've added things on the website, pull down on Summary to bring them in."
                />
            @endif

            @if ($this->deletedCount > 0)
                <pressable
                    ref="inventory-recently-deleted"
                    a11y-label="Recently deleted, {{ $this->deletedCount }}"
                    class="{{ Card::classes() }} min-h-12 w-full justify-center px-4 py-2"
                    @navigate('/inventory/deleted')
                >
                    <row class="w-full items-start gap-3">
                        <icon :ios="Ios::Trash" :android="Android::DeleteOutline" :size="20" class="text-theme-on-surface-variant" />
                        <text class="flex-1 text-base text-theme-on-surface">Recently deleted</text>
                        <text class="self-center text-base text-theme-on-surface-variant">{{ $this->deletedCount }}</text>
                        <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="self-center text-theme-on-surface-variant" />
                    </row>
                </pressable>
            @endif
        @endif
    </column>
</scroll-view>
