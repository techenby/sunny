@use('App\Enums\ItemType')
@use('App\Icons\Android')
@use('App\Icons\Ios')

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
    @endif
</native:top-bar>

@if ($item)
    <scroll-view ref="item-detail" fill class="bg-theme-background">
        <column class="w-full gap-6 px-4 pt-2 pb-8">
            <column class="w-full gap-3">
                <row class="w-full items-center gap-2">
                    <column class="h-7 w-7 items-center justify-center rounded-full bg-{{ $item['type']->iconColor() }}">
                        <icon :ios="$item['type']->iosIcon()" :android="$item['type']->androidIcon()" :size="14" class="text-white" />
                    </column>
                    <text ref="item-summary" class="flex-1 text-base text-theme-on-surface-variant">
                        {{ $this->parent ? $item['type']->label().' · in '.$this->parent['name'] : $item['type']->label() }}
                    </text>
                </row>

                @if ($item['photo_url'] ?? null)
                    <image
                        ref="item-photo"
                        :src="$item['photo_url']"
                        :alt="'Photo of '.$item['name']"
                        class="w-full h-[240] rounded-xl object-cover"
                    />
                @endif
            </column>

            @if ($this->path)
                <column class="w-full gap-3">
                    <text font="semibold" class="text-xl text-theme-on-background">Where it is</text>
                    <column class="w-full rounded-xl bg-theme-surface">
                        @foreach ($this->path as $ancestor)
                            <pressable
                                ref="{{ $loop->last ? 'item-parent' : 'item-ancestor-'.$ancestor['id'] }}"
                                @tap="openAncestor({{ $ancestor['id'] }})"
                                :a11y-label="$ancestor['name']"
                                class="w-full py-3 pr-4 pl-{{ 4 + $loop->index * 4 }}"
                            >
                                <row class="w-full items-center gap-3">
                                    <column class="h-8 w-8 items-center justify-center rounded-full bg-{{ $ancestor['type']->iconColor() }}">
                                        <icon :ios="$ancestor['type']->iosIcon()" :android="$ancestor['type']->androidIcon()" :size="16" class="text-white" />
                                    </column>
                                    <column class="flex-1 gap-0.5">
                                        <text font="semibold" class="text-base text-theme-on-surface">{{ $ancestor['name'] }}</text>
                                        <text class="text-sm text-theme-on-surface-variant">{{ $ancestor['type']->label() }}</text>
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
                    <text font="semibold" class="text-xl text-theme-on-background">Details</text>
                    <column class="w-full rounded-xl bg-theme-surface">
                        @foreach ($item['metadata'] as $key => $value)
                            <row ref="metadata-{{ $key }}" class="w-full items-center gap-3 px-4 py-3">
                                <text class="flex-1 text-base text-theme-on-surface-variant">{{ ucfirst($key) }}</text>
                                <text font="semibold" class="text-base text-theme-on-surface">{{ $value }}</text>
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
                        <text font="semibold" class="flex-1 text-xl text-theme-on-background">Contents</text>
                        @if ($this->children)
                            <text ref="item-contents-count" class="text-sm text-theme-on-surface-variant">
                                {{ trans_choice(':count item|:count items', count($this->children)) }}
                            </text>
                        @endif
                    </row>
                    <column class="w-full rounded-xl bg-theme-surface">
                        @foreach ($this->children as $child)
                            <pressable
                                ref="item-child-{{ $child['id'] }}"
                                @navigate('/inventory/'.$child['id'], ['from' => $item['id']])
                                :a11y-label="$child['name']"
                                class="w-full px-4 py-3"
                            >
                                <row class="w-full items-center gap-3">
                                    <column class="h-9 w-9 items-center justify-center rounded-full bg-{{ $child['type']->iconColor() }}">
                                        <icon :ios="$child['type']->iosIcon()" :android="$child['type']->androidIcon()" :size="18" class="text-white" />
                                    </column>
                                    <column class="flex-1 gap-0.5">
                                        <text font="semibold" class="text-base text-theme-on-surface">{{ $child['name'] }}</text>
                                        <text class="text-sm text-theme-on-surface-variant">
                                            {{ $child['children_count'] > 0 ? $child['type']->label().' · '.trans_choice(':count item|:count items', $child['children_count']) : $child['type']->label() }}
                                        </text>
                                    </column>
                                    <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                                </row>
                            </pressable>
                            <divider class="ml-16" />
                        @endforeach
                        <pressable
                            ref="item-add-child"
                            @navigate('/inventory/create', ['parent' => $item['id']])
                            :a11y-label="'Add an item inside '.$item['name']"
                            class="w-full px-4 py-3"
                        >
                            <row class="w-full items-center gap-3">
                                <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary/15">
                                    <icon :ios="Ios::Plus" :android="Android::Add" :size="18" class="text-theme-primary" />
                                </column>
                                <text font="semibold" class="flex-1 text-base text-theme-primary">Add item here</text>
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
