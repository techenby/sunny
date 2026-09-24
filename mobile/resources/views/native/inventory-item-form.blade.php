@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- Shared by the create and edit screens. Expects $formRef, $submitLabel,
     $typeOptions, $selectedParent, $browsedParent,
     $parentPickerRows, and optionally $subtitle; the remaining
     state comes from the including component's ManagesInventoryItemForm
     properties. Computed values have to be passed in because an @include is
     not bound to $this. --}}

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        @isset($subtitle)
            <text ref="{{ $formRef }}-subtitle" class="text-base text-theme-on-surface-variant">
                {{ $subtitle }}
            </text>
        @endisset

        <column class="w-full gap-4">
            <outlined-text-input
                ref="{{ $formRef }}-name"
                label="Name"
                placeholder="Camping tent"
                autocapitalize="sentences"
                :ios-leading-icon="Ios::Tag"
                :android-leading-icon="Android::Label"
                native:model.blur="name"
            />

            @include('native.photo-field', [
                'photoPath' => $photoPath ?? $existingPhotoUrl,
                'refPrefix' => $formRef,
                'alt' => 'The photo chosen for this item',
            ])

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Type</text>
                <button-group
                    ref="{{ $formRef }}-type"
                    :options="$typeOptions"
                    a11y-label="Item type"
                    native:model="typeIndex"
                />
            </column>

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Inside</text>
                <pressable
                    ref="{{ $formRef }}-parent"
                    :a11y-label="'Inside '.($selectedParent['name'] ?? 'Top level')"
                    a11y-hint="Choose where this item lives"
                    class="h-16 w-full justify-center rounded-xl border border-theme-outline bg-theme-surface px-4"
                    @tap="openParentPicker"
                >
                    <row class="w-full items-center gap-3">
                        @if ($selectedParent)
                            <column class="h-8 w-8 items-center justify-center rounded-full bg-{{ $selectedParent['type']->iconColor() }}">
                                <icon :ios="$selectedParent['type']->iosIcon()" :android="$selectedParent['type']->androidIcon()" :size="16" class="text-white" />
                            </column>
                        @else
                            <column class="h-8 w-8 items-center justify-center rounded-full bg-theme-surface-variant">
                                <icon :ios="Ios::House" :android="Android::Home" :size="16" class="text-theme-on-surface-variant" />
                            </column>
                        @endif
                        <column class="flex-1 gap-0.5">
                            <text font="semibold" class="text-base text-theme-on-surface">{{ $selectedParent['name'] ?? 'Top level' }}</text>
                            @if ($selectedParent['path'] ?? null)
                                <text class="text-sm text-theme-on-surface-variant" :max-lines="1">in {{ $selectedParent['path'] }}</text>
                            @endif
                        </column>
                        <icon :ios="Ios::ChevronUpChevronDown" :android="Android::UnfoldMore" :size="14" class="text-theme-on-surface-variant" />
                    </row>
                </pressable>
            </column>
        </column>

        <column class="w-full gap-2">
            <text class="text-sm font-semibold text-theme-on-surface-variant">Metadata</text>

            @foreach ($metadata as $index => $pair)
                <row class="w-full items-center gap-2">
                    <outlined-text-input
                        ref="metadata-key-{{ $index }}"
                        placeholder="Key"
                        class="flex-1"
                        a11y-label="Metadata key {{ $index + 1 }}"
                        :value="$pair['key']"
                        sync-mode="blur"
                        @change="setMetadataKey({{ $index }})"
                    />
                    <outlined-text-input
                        ref="metadata-value-{{ $index }}"
                        placeholder="Value"
                        class="flex-1"
                        a11y-label="Metadata value {{ $index + 1 }}"
                        :value="$pair['value']"
                        sync-mode="blur"
                        @change="setMetadataValue({{ $index }})"
                    />
                    <pressable
                        ref="metadata-remove-{{ $index }}"
                        class="h-12 w-12 items-center justify-center"
                        a11y-label="Remove metadata field {{ $index + 1 }}"
                        @tap="removeMetadata({{ $index }})"
                    >
                        <native:icon
                            :ios="Ios::Xmark"
                            :android="Android::Close"
                            :size="20"
                            class="text-theme-on-surface-variant"
                        />
                    </pressable>
                </row>
            @endforeach

            <pressable
                ref="metadata-add"
                class="h-12 items-start justify-center self-start px-2"
                @tap="addMetadata"
            >
                <text class="text-sm font-semibold text-theme-primary">Add field</text>
            </pressable>
        </column>

        @if ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-sm text-theme-destructive">
                {{ $error }}
            </text>
        @endif

        <button
            ref="{{ $formRef }}-submit"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            :disabled="$saving || $savedId !== null"
            @tap="save"
        >
            {{ $submitLabel }}
        </button>
    </column>
</scroll-view>

<native:bottom-sheet ref="{{ $formRef }}-parent-picker" :visible="$showParentPicker" @dismiss="closeParentPicker" detents="large">
    {{-- Rows are only built while the sheet is open: a large inventory would
         otherwise bloat every render of the form. --}}
    @if ($showParentPicker)
        {{-- The search field lives inside the list: iOS lists ignore bg-* classes
             and paint the system grouped background, so a separate header
             would never match. --}}
        <list ref="{{ $formRef }}-parent-list" fill separator>
            <list-section>
                <column class="w-full px-4 py-2">
                    <outlined-text-input
                        ref="{{ $formRef }}-parent-search"
                        placeholder="Search locations, bins, and items"
                        a11y-label="Search destinations"
                        :ios-leading-icon="Ios::Magnifyingglass"
                        :android-leading-icon="Android::Search"
                        native:model.debounce.300ms="parentSearch"
                    />
                </column>
            </list-section>

            @if ($parentSearch !== '')
                <list-section>
                    @forelse ($parentPickerRows as $choice)
                        <list-item
                            ref="{{ $formRef }}-parent-{{ $choice['id'] }}"
                            :headline="$choice['name']"
                            :supporting="$choice['path'] ? $choice['type']->label().' · in '.$choice['path'] : $choice['type']->label()"
                            :leadingIconIos="$choice['type']->iosIcon()"
                            :leadingIconAndroid="$choice['type']->androidIcon()"
                            :leadingIconBgColor="$choice['type']->iconColor()"
                            :trailingIconIos="($selectedParent['id'] ?? null) === $choice['id'] ? Ios::Checkmark : null"
                            :trailingIconAndroid="($selectedParent['id'] ?? null) === $choice['id'] ? Android::Check : null"
                            :trailingIconColor="theme('primary')"
                            @tap="selectParent({{ $choice['id'] }})"
                        />
                    @empty
                        <list-item
                            ref="{{ $formRef }}-parent-no-matches"
                            :headline="'Nothing matches “'.$parentSearch.'”'"
                            supporting="Try a different search."
                            :leadingIconIos="Ios::Magnifyingglass"
                            :leadingIconAndroid="Android::SearchOff"
                            :leadingIconColor="theme('on-surface-variant')"
                        />
                    @endforelse
                </list-section>
            @else
                <list-section>
                    @if ($browsedParent)
                        <list-item
                            ref="{{ $formRef }}-parent-up"
                            :headline="$browsedParent['path'] ? 'Back to '.\Illuminate\Support\Str::afterLast($browsedParent['path'], ' › ') : 'Back to all locations'"
                            :leadingIconIos="Ios::ChevronLeft"
                            :leadingIconAndroid="Android::ChevronLeft"
                            :leadingIconColor="theme('primary')"
                            :headlineColor="theme('primary')"
                            @tap="browseUp"
                        />
                        <list-item
                            ref="{{ $formRef }}-parent-here"
                            :headline="'Inside '.$browsedParent['name']"
                            :supporting="$browsedParent['path'] ? $browsedParent['type']->label().' · in '.$browsedParent['path'] : $browsedParent['type']->label()"
                            :leadingIconIos="$browsedParent['type']->iosIcon()"
                            :leadingIconAndroid="$browsedParent['type']->androidIcon()"
                            :leadingIconBgColor="$browsedParent['type']->iconColor()"
                            :trailingIconIos="($selectedParent['id'] ?? null) === $browsedParent['id'] ? Ios::Checkmark : null"
                            :trailingIconAndroid="($selectedParent['id'] ?? null) === $browsedParent['id'] ? Android::Check : null"
                            :trailingIconColor="theme('primary')"
                            @tap="selectParent({{ $browsedParent['id'] }})"
                        />
                    @else
                        <list-item
                            ref="{{ $formRef }}-parent-top-level"
                            headline="Top level"
                            supporting="Not inside anything"
                            :leadingIconIos="Ios::House"
                            :leadingIconAndroid="Android::Home"
                            :leadingIconColor="theme('on-surface-variant')"
                            :trailingIconIos="$selectedParent === null ? Ios::Checkmark : null"
                            :trailingIconAndroid="$selectedParent === null ? Android::Check : null"
                            :trailingIconColor="theme('primary')"
                            @tap="selectTopLevel"
                        />
                    @endif
                </list-section>

                @if ($parentPickerRows !== [])
                    <list-section :header="$browsedParent ? 'In '.$browsedParent['name'] : 'Locations'">
                        @foreach ($parentPickerRows as $choice)
                            @if ($choice['children_count'] > 0)
                                <list-item
                                    ref="{{ $formRef }}-parent-{{ $choice['id'] }}"
                                    :headline="$choice['name']"
                                    :supporting="$choice['type']->label().' · '.trans_choice(':count item|:count items', $choice['children_count'])"
                                    :leadingIconIos="$choice['type']->iosIcon()"
                                    :leadingIconAndroid="$choice['type']->androidIcon()"
                                    :leadingIconBgColor="$choice['type']->iconColor()"
                                    :trailingIconIos="Ios::ChevronRight"
                                    :trailingIconAndroid="Android::ChevronRight"
                                    @tap="browseParent({{ $choice['id'] }})"
                                />
                            @else
                                <list-item
                                    ref="{{ $formRef }}-parent-{{ $choice['id'] }}"
                                    :headline="$choice['name']"
                                    :supporting="$choice['type']->label()"
                                    :leadingIconIos="$choice['type']->iosIcon()"
                                    :leadingIconAndroid="$choice['type']->androidIcon()"
                                    :leadingIconBgColor="$choice['type']->iconColor()"
                                    :trailingIconIos="($selectedParent['id'] ?? null) === $choice['id'] ? Ios::Checkmark : null"
                                    :trailingIconAndroid="($selectedParent['id'] ?? null) === $choice['id'] ? Android::Check : null"
                                    :trailingIconColor="theme('primary')"
                                    @tap="selectParent({{ $choice['id'] }})"
                                />
                            @endif
                        @endforeach
                    </list-section>
                @endif
            @endif
        </list>
    @endif
</native:bottom-sheet>
