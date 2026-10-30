@use('App\Enums\ItemType')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\Inventory')
@use('App\Ui\Card')

{{-- The destination picker sheet. Expects $refPrefix, $selectedParent,
     $browsedParent, $parentPickerRows and $parentTrail, and optionally
     $topLevelLabel for the button at Home; the remaining state
     and actions come from the including component's ChoosesInventoryParent
     trait. Computed values have to be passed in because an @include is not
     bound to $this. Tapping a row only ever looks inside it; the button at
     the bottom is the one way to choose. --}}

<native:bottom-sheet ref="{{ $refPrefix }}-parent-picker" :visible="$showParentPicker" @dismiss="closeParentPicker" detents="large">
    {{-- Rows are only built while the sheet is open: a large inventory would
         otherwise bloat every render of the form. --}}
    @if ($showParentPicker)
        @php
            [$openableRows, $looseRows] = collect($parentPickerRows)
                ->partition(fn (array $choice): bool => $choice['type'] !== ItemType::Item || $choice['children_count'] > 0)
                ->map(fn ($rows): array => $rows->values()->all())->all();
        @endphp

        <column fill class="bg-theme-background">
            <column class="w-full gap-3 px-4 pt-4 pb-2">
                <row class="w-full items-center">
                    <pressable ref="{{ $refPrefix }}-parent-cancel" class="min-h-12 w-20 justify-center" @tap="closeParentPicker">
                        <text class="text-lg text-theme-primary">Cancel</text>
                    </pressable>
                    <text font="semibold" class="flex-1 text-center text-lg text-theme-on-surface">Where does it go?</text>
                    <column class="w-20" />
                </row>
                <outlined-text-input
                    ref="{{ $refPrefix }}-parent-search"
                    placeholder="Find a place, shelf or box"
                    a11y-label="Find a place"
                    :ios-leading-icon="Ios::Magnifyingglass"
                    :android-leading-icon="Android::Search"
                    native:model.debounce.300ms="parentSearch"
                />
            </column>

            <scroll-view ref="{{ $refPrefix }}-parent-list" fill>
                <column class="w-full gap-4 px-4 pt-2 pb-6">
                    @if ($parentSearch !== '')
                        @if ($parentPickerRows)
                            <column class="{{ Card::classes() }} w-full">
                                @foreach ($parentPickerRows as $choice)
                                    <pressable
                                        ref="{{ $refPrefix }}-parent-{{ $choice['id'] }}"
                                        :a11y-label="$choice['name']"
                                        class="min-h-12 w-full justify-center px-4 py-2"
                                        @tap="selectParent({{ $choice['id'] }})"
                                    >
                                        <row class="w-full items-center gap-4">
                                            <x-ui.avatar :color="$choice['type']->color()" :ios="$choice['type']->iosIcon()" :android="$choice['type']->androidIcon()" />
                                            <column class="flex-1 gap-0.5">
                                                <x-ui.heading size="lg">{{ $choice['name'] }}</x-ui.heading>
                                                <x-ui.text>{{ $choice['path'] ? 'In '.$choice['path'] : $choice['type']->label() }}</x-ui.text>
                                            </column>
                                        </row>
                                    </pressable>
                                    @unless ($loop->last)
                                        <divider class="ml-[72]" />
                                    @endunless
                                @endforeach
                            </column>
                        @else
                            <column ref="{{ $refPrefix }}-parent-no-matches" class="w-full items-center gap-2 px-4 py-8">
                                <x-ui.heading size="lg">Nothing called “{{ $parentSearch }}”</x-ui.heading>
                                <x-ui.text>Check the spelling, or try a shorter word.</x-ui.text>
                            </column>
                        @endif
                    @else
                        <row ref="{{ $refPrefix }}-parent-trail" class="w-full flex-wrap items-center gap-2">
                            @if ($parentTrail)
                                <pressable ref="{{ $refPrefix }}-parent-crumb-home" class="min-h-12 justify-center rounded-full bg-theme-surface-variant px-3" @tap="browseTop">
                                    <row class="items-center gap-1.5">
                                        <icon :ios="Ios::House" :android="Android::Home" :size="14" class="text-theme-on-surface" />
                                        <text class="text-base text-theme-on-surface">Home</text>
                                    </row>
                                </pressable>
                                @foreach ($parentTrail as $crumb)
                                    <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="12" class="text-theme-on-surface-variant" />
                                    @if ($loop->last)
                                        <text font="semibold" class="text-base text-theme-on-surface">{{ $crumb['name'] }}</text>
                                    @else
                                        <pressable ref="{{ $refPrefix }}-parent-crumb-{{ $crumb['id'] }}" class="min-h-12 justify-center rounded-full bg-theme-surface-variant px-3" @tap="browseParent({{ $crumb['id'] }})">
                                            <text class="text-base text-theme-on-surface">{{ $crumb['name'] }}</text>
                                        </pressable>
                                    @endif
                                @endforeach
                            @else
                                <row class="items-center gap-1.5">
                                    <icon :ios="Ios::House" :android="Android::Home" :size="14" class="text-theme-on-surface" />
                                    <text font="semibold" class="text-base text-theme-on-surface">Home</text>
                                </row>
                            @endif
                        </row>

                        @if ($openableRows)
                            <column class="w-full gap-2">
                                <x-ui.subheading class="px-1">{{ $browsedParent ? 'Inside '.$browsedParent['name'] : 'Places in your home' }}</x-ui.subheading>
                                <column class="{{ Card::classes() }} w-full">
                                    @foreach ($openableRows as $choice)
                                        <pressable
                                            ref="{{ $refPrefix }}-parent-{{ $choice['id'] }}"
                                            :a11y-label="$choice['name']"
                                            class="min-h-12 w-full justify-center px-4 py-2"
                                            @tap="browseParent({{ $choice['id'] }})"
                                        >
                                            <row class="w-full items-center gap-4">
                                                <x-ui.avatar :color="$choice['type']->color()" :ios="$choice['type']->iosIcon()" :android="$choice['type']->androidIcon()" />
                                                <column class="flex-1 gap-0.5">
                                                    <x-ui.heading size="lg">{{ $choice['name'] }}</x-ui.heading>
                                                    <x-ui.text>{{ Inventory::summary($choice) }}</x-ui.text>
                                                </column>
                                                <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                                            </row>
                                        </pressable>
                                        @unless ($loop->last)
                                            <divider class="ml-[72]" />
                                        @endunless
                                    @endforeach
                                </column>
                            </column>
                        @endif

                        @if ($looseRows)
                            <x-ui.text ref="{{ $refPrefix }}-parent-loose" class="px-1">
                                {{ ($openableRows ? 'Also here: ' : 'Here: ').collect($looseRows)->pluck('name')->implode(', ') }}
                            </x-ui.text>
                        @elseif (! $openableRows && $browsedParent)
                            <x-ui.text class="px-1">Nothing inside yet.</x-ui.text>
                        @endif
                    @endif
                </column>
            </scroll-view>

            @if ($parentSearch === '')
                <column class="w-full gap-2 border-t border-theme-outline bg-theme-surface px-4 pt-3 pb-8">
                    @if ($browsedParent)
                        <button
                            ref="{{ $refPrefix }}-parent-choose"
                            variant="primary"
                            size="lg"
                            font="semibold"
                            class="w-full"
                            @tap="selectParent({{ $browsedParent['id'] }})"
                        >
                            Put it in {{ $browsedParent['name'] }}
                        </button>
                    @else
                        <button
                            ref="{{ $refPrefix }}-parent-choose"
                            variant="primary"
                            size="lg"
                            font="semibold"
                            class="w-full"
                            @tap="selectTopLevel"
                        >
                            {{ $topLevelLabel ?? 'Don’t put it in a place yet' }}
                        </button>
                    @endif
                    @if ($openableRows)
                        <text class="text-center text-sm text-theme-on-surface-variant">Or tap one above to look inside it</text>
                    @endif
                </column>
            @endif
        </column>
    @endif
</native:bottom-sheet>
