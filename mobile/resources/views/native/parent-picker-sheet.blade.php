@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- The destination picker sheet. Expects $refPrefix, $selectedParent,
     $browsedParent and $parentPickerRows; the remaining state and actions
     come from the including component's ChoosesInventoryParent trait.
     Computed values have to be passed in because an @include is not bound
     to $this. --}}

<native:bottom-sheet ref="{{ $refPrefix }}-parent-picker" :visible="$showParentPicker" @dismiss="closeParentPicker" detents="large">
    {{-- Rows are only built while the sheet is open: a large inventory would
         otherwise bloat every render of the form. --}}
    @if ($showParentPicker)
        {{-- The search field lives inside the list: iOS lists ignore bg-* classes
             and paint the system grouped background, so a separate header
             would never match. --}}
        <list ref="{{ $refPrefix }}-parent-list" fill separator>
            <list-section>
                <column class="w-full px-4 py-2">
                    <outlined-text-input
                        ref="{{ $refPrefix }}-parent-search"
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
                            ref="{{ $refPrefix }}-parent-{{ $choice['id'] }}"
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
                            ref="{{ $refPrefix }}-parent-no-matches"
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
                            ref="{{ $refPrefix }}-parent-up"
                            :headline="$browsedParent['path'] ? 'Back to '.\Illuminate\Support\Str::afterLast($browsedParent['path'], ' › ') : 'Back to all locations'"
                            :leadingIconIos="Ios::ChevronLeft"
                            :leadingIconAndroid="Android::ChevronLeft"
                            :leadingIconColor="theme('primary')"
                            :headlineColor="theme('primary')"
                            @tap="browseUp"
                        />
                        <list-item
                            ref="{{ $refPrefix }}-parent-here"
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
                            ref="{{ $refPrefix }}-parent-top-level"
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
                                    ref="{{ $refPrefix }}-parent-{{ $choice['id'] }}"
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
                                    ref="{{ $refPrefix }}-parent-{{ $choice['id'] }}"
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
