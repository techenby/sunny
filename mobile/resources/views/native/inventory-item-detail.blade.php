@use('App\Enums\ItemType')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

@php($item = $this->item)

<native:top-bar :title="$item['name'] ?? 'Item'" display-mode="large" back>
    @if ($item)
        <native:top-bar-action
            ref="edit-item"
            id="edit-item"
            label="Edit"
            :ios-icon="Ios::Pencil"
            :android-icon="Android::Edit"
            @navigate('/inventory/'.$item['id'].'/edit')
        />
        <native:top-bar-action
            ref="delete-item"
            id="delete-item"
            label="Delete"
            :ios-icon="Ios::Trash"
            :android-icon="Android::Delete"
            @tap="confirmDeleteItem"
        />
    @endif
</native:top-bar>

@if ($item)
    <scroll-view ref="item-detail" fill class="bg-theme-background">
        <column class="w-full gap-6 px-4 pt-2 pb-8">
            <column class="w-full gap-3">
                <row class="w-full items-center gap-2">
                    <x-ui.avatar
                        size="xs"
                        :color="$item['type']->color()"
                        :ios="$item['type']->iosIcon()"
                        :android="$item['type']->androidIcon()"
                    />
                    <x-ui.text ref="item-summary" class="flex-1">
                        {{ $this->parent ? $item['type']->label().' · in '.$this->parent['name'] : $item['type']->label() }}
                    </x-ui.text>
                </row>

                @include('native.queued-change', ['refPrefix' => 'item', 'queuedChange' => $this->queuedChange])

                @if ($error !== '')
                    <text ref="item-error" class="text-sm text-theme-destructive">{{ $error }}</text>
                @endif

                @if ($item['photo_url'] ?? null)
                    <image
                        ref="item-photo"
                        :src="$item['photo_url']"
                        :alt="'Photo of '.$item['name']"
                        class="w-full h-[240] rounded-[18] object-cover"
                    />
                @endif
            </column>

            @if ($this->path)
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Where it is</x-ui.heading>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->path as $ancestor)
                            <pressable
                                ref="{{ $loop->last ? 'item-parent' : 'item-ancestor-'.$ancestor['id'] }}"
                                @tap="openAncestor({{ $ancestor['id'] }})"
                                :a11y-label="$ancestor['name']"
                                class="w-full py-3 pr-4 pl-{{ 4 + $loop->index * 4 }}"
                            >
                                <row class="w-full items-center gap-3">
                                    <x-ui.avatar
                                        size="sm"
                                        :color="$ancestor['type']->color()"
                                        :ios="$ancestor['type']->iosIcon()"
                                        :android="$ancestor['type']->androidIcon()"
                                    />
                                    <column class="flex-1 gap-0.5">
                                        <x-ui.heading>{{ $ancestor['name'] }}</x-ui.heading>
                                        <x-ui.text size="sm">{{ $ancestor['type']->label() }}</x-ui.text>
                                    </column>
                                    <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                                </row>
                            </pressable>
                            @unless ($loop->last)
                                <divider class="ml-4" />
                            @endunless
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($item['metadata'])
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Details</x-ui.heading>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($item['metadata'] as $key => $value)
                            <row ref="metadata-{{ $key }}" class="w-full items-center gap-3 px-4 py-3">
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

            @if ($this->children || $item['type'] !== ItemType::Item)
                <column class="w-full gap-3">
                    <row class="w-full items-center">
                        <x-ui.heading size="lg" class="flex-1">Contents</x-ui.heading>
                        @if ($this->children)
                            <text ref="item-contents-count" class="text-sm text-theme-on-surface-variant">
                                {{ trans_choice(':count item|:count items', count($this->children)) }}
                            </text>
                        @endif
                    </row>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->children as $child)
                            <pressable
                                ref="item-child-{{ $child['id'] }}"
                                @navigate('/inventory/'.$child['id'], ['from' => $item['id']])
                                :a11y-label="$child['name']"
                                class="w-full px-4 py-3"
                            >
                                <row class="w-full items-center gap-4">
                                    <x-ui.avatar
                                        :size="$child['photo_url'] ? '2xl' : 'md'"
                                        :src="$child['photo_url']"
                                        :alt="'Photo of '.$child['name']"
                                        :color="$child['type']->color()"
                                        :ios="$child['type']->iosIcon()"
                                        :android="$child['type']->androidIcon()"
                                    />
                                    <column class="flex-1 gap-0.5">
                                        <x-ui.heading>{{ $child['name'] }}</x-ui.heading>
                                        <x-ui.text size="sm">
                                            {{ $child['children_count'] > 0 ? $child['type']->label().' · '.trans_choice(':count item|:count items', $child['children_count']) : $child['type']->label() }}
                                        </x-ui.text>
                                    </column>
                                    <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                                </row>
                            </pressable>
                            <divider class="ml-[72]" />
                        @endforeach
                        <pressable
                            ref="item-add-child"
                            @navigate('/inventory/create', ['parent' => $item['id']])
                            :a11y-label="'Add an item inside '.$item['name']"
                            class="w-full px-4 py-3"
                        >
                            <row class="w-full items-center gap-4">
                                <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                                    <icon :ios="Ios::Plus" :android="Android::Add" :size="24" class="text-theme-primary" />
                                </column>
                                <x-ui.heading accent class="flex-1">Add item here</x-ui.heading>
                            </row>
                        </pressable>
                        <divider class="ml-[72]" />
                        <pressable
                            ref="item-scan-children"
                            @navigate('/inventory/scan', ['parent' => $item['id']])
                            :a11y-label="'Scan items into '.$item['name']"
                            class="w-full px-4 py-3"
                        >
                            <row class="w-full items-center gap-4">
                                <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                                    <icon :ios="Ios::CameraViewfinder" :android="Android::DocumentScanner" :size="24" class="text-theme-primary" />
                                </column>
                                <x-ui.heading accent class="flex-1">Scan items here</x-ui.heading>
                            </row>
                        </pressable>
                    </column>
                </column>
            @endif
        </column>
    </scroll-view>
@else
    <column ref="item-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This item could not be found.
        </text>
    </column>
@endif
