@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($checklist = $this->checklist)

<native:top-bar :title="$checklist['name'] ?? 'List'" back>
    @if ($checklist)
        <native:top-bar-action
            ref="list-actions"
            id="list-actions"
            label="More"
            :ios-icon="Ios::Ellipsis"
            :android-icon="Android::MoreVert"
        >
            @if ($this->completedItems)
                <native:top-bar-action
                    native:key="remove-done"
                    ref="remove-done"
                    id="remove-done"
                    label="Remove done"
                    :ios-icon="Ios::CheckmarkCircle"
                    :android-icon="Android::RemoveDone"
                    @tap="confirmRemoveDone"
                />
                <native:top-bar-action
                    native:key="uncheck-all"
                    ref="uncheck-all"
                    id="uncheck-all"
                    label="Uncheck all"
                    :ios-icon="Ios::ArrowCounterclockwise"
                    :android-icon="Android::Replay"
                    @tap="uncheckAll"
                />
                <native:top-bar-action native:key="list-actions-divider" id="list-actions-divider" divider />
            @endif
            <native:top-bar-action
                ref="edit-list"
                id="edit-list"
                label="Edit list"
                :ios-icon="Ios::Pencil"
                :android-icon="Android::Edit"
                @navigate('/lists/'.$checklist['id'].'/edit')
            />
            <native:top-bar-action
                ref="delete-list"
                id="delete-list"
                label="Delete list"
                destructive
                :ios-icon="Ios::Trash"
                :android-icon="Android::Delete"
                @tap="confirmDeleteList"
            />
        </native:top-bar-action>
    @endif
</native:top-bar>

@if ($checklist)
    @php($editingItem = $editingItemId !== null ? collect($this->items)->firstWhere('id', $editingItemId) : null)

    <native:bottom-bar>
        @if ($editingItem)
            @php($showsLinkField = $this->takesLinks || $editingItem['url'] !== null)
            <column class="w-full px-3 pb-2">
                <column ref="list-edit-item" class="w-full gap-2.5 rounded-[24] p-3 glass android:bg-theme-surface">
                    <row class="w-full items-center">
                        <text class="flex-1 text-sm text-theme-on-surface-variant">Edit item</text>
                        <pressable ref="list-edit-cancel" a11y-label="Cancel" class="min-h-8 justify-center pl-3" @tap="cancelEdit">
                            <text class="text-sm text-theme-on-surface-variant">Cancel</text>
                        </pressable>
                    </row>

                    <column class="w-full gap-1">
                        <text class="text-xs text-theme-on-surface-variant">Name</text>
                        <row class="min-h-11 w-full items-center rounded-xl border border-theme-outline bg-theme-surface px-3">
                            <bare-text-input
                                ref="list-edit-name"
                                class="flex-1"
                                a11y-label="Item name"
                                autocapitalize="sentences"
                                autofocus
                                native:model="editingName"
                                @submit="saveItem"
                            />
                        </row>
                    </column>

                    @if ($showsLinkField)
                        <column class="w-full gap-1">
                            <text class="text-xs text-theme-on-surface-variant">Link</text>
                            <row class="min-h-11 w-full items-center gap-2 rounded-xl border bg-theme-surface pr-1 pl-3 {{ $linkError !== '' ? 'border-theme-primary' : 'border-theme-outline' }}">
                                <icon :ios="Ios::Link" :android="Android::Link" :size="16" class="text-theme-primary" />
                                <bare-text-input
                                    ref="list-edit-url"
                                    class="flex-1"
                                    placeholder="Link"
                                    a11y-label="Link"
                                    keyboard="url"
                                    native:model="editingUrl"
                                    @submit="submitEditingUrl"
                                />
                                @if ($editingUrl !== '')
                                    <pressable ref="list-edit-url-clear" a11y-label="Clear link" class="h-8 w-8 items-center justify-center" @tap="clearEditingUrl">
                                        <icon :ios="Ios::XmarkCircleFill" :android="Android::Cancel" :size="18" class="text-theme-on-surface-variant" />
                                    </pressable>
                                @endif
                            </row>
                            @if ($linkError !== '')
                                @include('native.list-link-error')
                            @endif
                        </column>
                    @endif

                    @if ($error !== '')
                        <text ref="list-edit-error" class="text-sm text-theme-destructive">{{ $error }}</text>
                    @endif

                    <row class="w-full items-center gap-3">
                        @if ($editingItem['url'] !== null)
                            <pressable ref="list-edit-remove-link" a11y-label="Remove link" class="min-h-10 justify-center" @tap="removeLink">
                                <row class="items-center gap-1.5">
                                    <icon :ios="Ios::Xmark" :android="Android::LinkOff" :size="14" class="text-theme-primary-text" />
                                    <text font="semibold" class="text-sm text-theme-primary-text">Remove link</text>
                                </row>
                            </pressable>
                        @endif
                        <column class="flex-1" />
                        <pressable ref="list-edit-save" a11y-label="Save" class="min-h-10 shrink-0 items-center justify-center rounded-full bg-theme-primary px-5" @tap="saveItem">
                            <text font="semibold" class="text-base text-theme-on-primary">Save</text>
                        </pressable>
                    </row>
                </column>
            </column>
        @elseif ($this->takesLinks)
            <column class="w-full px-3 pb-2">
                <column ref="list-new-item-card" class="w-full gap-1.5 rounded-[24] p-2 glass android:bg-theme-surface">
                    <row class="min-h-10 w-full items-center px-3">
                        <bare-text-input
                            ref="list-new-item"
                            class="flex-1"
                            placeholder="Add an item"
                            a11y-label="Add an item"
                            autocapitalize="sentences"
                            next-focus="list-new-item-url"
                            native:model="newItem"
                        />
                    </row>
                    <row class="w-full items-center gap-2">
                        <row class="min-h-12 flex-1 items-center gap-2 rounded-full border bg-theme-surface pr-1 pl-3 {{ $linkError !== '' ? 'border-theme-primary' : 'border-theme-outline' }}">
                            <icon :ios="Ios::Link" :android="Android::Link" :size="16" class="text-theme-primary" />
                            <bare-text-input
                                ref="list-new-item-url"
                                class="flex-1"
                                placeholder="Link"
                                a11y-label="Link, optional"
                                keyboard="url"
                                next-focus="list-new-item"
                                native:model="newItemUrl"
                                @submit="submitNewItemUrl"
                            />
                            @if ($newItemUrl !== '')
                                <pressable ref="list-new-item-url-clear" a11y-label="Clear link" class="h-8 w-8 items-center justify-center" @tap="clearNewItemUrl">
                                    <icon :ios="Ios::XmarkCircleFill" :android="Android::Cancel" :size="18" class="text-theme-on-surface-variant" />
                                </pressable>
                            @endif
                        </row>
                        <pressable
                            ref="list-add-item"
                            a11y-label="Add item"
                            class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
                            @tap="addItem"
                        >
                            <native:icon :ios="Ios::Plus" :android="Android::Add" :size="22" class="text-theme-on-surface" />
                        </pressable>
                    </row>
                    @if ($linkError !== '')
                        @include('native.list-link-error')
                    @else
                        <text class="px-3 pb-1 text-xs text-theme-on-surface-variant">Link is optional. Type it, or paste one in.</text>
                    @endif
                </column>
            </column>
        @else
            <column class="w-full gap-3 px-4 pb-2">
                @if ($uncheckedIds !== [])
                    <row native:key="list-unchecked" ref="list-unchecked" class="w-full items-center gap-3 rounded-[18] py-1 pr-2 pl-3.5 glass android:bg-theme-surface">
                        <column class="h-8 w-8 items-center justify-center rounded-full bg-theme-primary">
                            <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="16" class="text-theme-on-primary" />
                        </column>
                        <text font="semibold" class="flex-1 text-base text-theme-on-surface">{{ trans_choice(':count item unchecked|:count items unchecked', count($uncheckedIds)) }}</text>
                        <pressable
                            ref="list-undo-uncheck"
                            a11y-label="Undo"
                            a11y-hint="Checks those items again"
                            class="min-h-12 items-center justify-center"
                            @tap="undoUncheckAll"
                        >
                            <column class="h-9 items-center justify-center rounded-full bg-theme-primary/15 px-3.5">
                                <text font="semibold" class="text-base text-theme-primary-text">Undo</text>
                            </column>
                        </pressable>
                    </row>
                @endif
                <row class="w-full items-center gap-3">
                    <row class="h-12 flex-1 items-center gap-2 rounded-full px-4 glass android:bg-theme-surface">
                        <bare-text-input
                            ref="list-new-item"
                            class="flex-1"
                            placeholder="Add an item"
                            a11y-label="Add an item"
                            autocapitalize="sentences"
                            keep-focus-on-submit
                            native:model="newItem"
                            @submit="addItem"
                        />
                    </row>
                    <pressable
                        ref="list-add-item"
                        a11y-label="Add item"
                        class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
                        @tap="addItem"
                    >
                        <native:icon :ios="Ios::Plus" :android="Android::Add" :size="22" class="text-theme-on-surface" />
                    </pressable>
                </row>
            </column>
        @endif
    </native:bottom-bar>

    <scroll-view ref="list-detail" fill class="bg-theme-background">
        <column class="w-full gap-4 px-4 pt-2 pb-8">
            <row class="w-full items-start gap-2">
                <icon :ios="$checklist['type']->iosIcon()" :android="$checklist['type']->androidIcon()" :size="16" class="mt-0.5 text-theme-on-surface-variant" />
                <text ref="list-summary" class="flex-1 text-sm text-theme-on-surface-variant">{{ $this->summary }}</text>
            </row>

            @include('native.queued-change', ['refPrefix' => 'list', 'queuedChange' => $this->queuedChange])

            @if ($error !== '' && ! $editingItem)
                <text ref="list-error" class="text-sm text-theme-destructive">{{ $error }}</text>
            @endif

            @if ($this->items)
                <column class="w-full rounded-xl bg-theme-surface">
                    @foreach ($this->items as $item)
                        <row class="w-full items-center {{ $editingItemId === $item['id'] ? 'bg-theme-primary/10' : '' }}">
                            <column class="flex-1">
                                <pressable
                                    ref="list-item-{{ $item['id'] }}"
                                    @press="toggleItem({{ $item['id'] }})"
                                    :a11y-label="($item['completed'] ? 'Checked: ' : '').$item['name']"
                                    class="w-full pt-3 pl-4 {{ $item['domain'] ? 'pb-0.5' : 'pb-3' }}"
                                >
                                    <row class="w-full items-start gap-3">
                                        <icon
                                            :ios="$item['completed'] ? Ios::CheckmarkCircleFill : Ios::Circle"
                                            :android="$item['completed'] ? Android::CheckCircle : Android::RadioButtonUnchecked"
                                            :size="22"
                                            :class="$item['completed'] ? 'text-theme-primary' : 'text-theme-outline'"
                                        />
                                        <column class="flex-1 gap-0.5">
                                            <text :class="$item['completed'] ? 'text-base text-theme-on-surface-variant line-through' : 'text-base text-theme-on-surface'">{{ $item['name'] }}</text>
                                            @if ($item['error'])
                                                <text ref="list-item-{{ $item['id'] }}-error" class="text-sm text-theme-destructive">Not saved to Sunny. {{ $item['error'] }}</text>
                                            @endif
                                        </column>
                                    </row>
                                </pressable>
                                @if ($item['domain'])
                                    <row class="w-full pb-2 pl-[50]">
                                        <pressable
                                            ref="list-item-{{ $item['id'] }}-link"
                                            :a11y-label="'Open link for '.$item['name']"
                                            :a11y-hint="'Opens '.$item['domain'].' in the browser'"
                                            class="py-1"
                                            @tap="openItemLink({{ $item['id'] }})"
                                        >
                                            <row class="items-center gap-1">
                                                <icon :ios="Ios::Link" :android="Android::Link" :size="12" :class="$item['completed'] ? 'text-theme-on-surface-variant' : 'text-theme-primary-text'" />
                                                <text ref="list-item-{{ $item['id'] }}-domain" font="semibold" max-lines="1" :class="$item['completed'] ? 'text-sm text-theme-on-surface-variant' : 'text-sm text-theme-primary-text'">{{ $item['domain'] }}</text>
                                            </row>
                                        </pressable>
                                    </row>
                                @endif
                            </column>
                            <pressable
                                ref="list-item-{{ $item['id'] }}-actions"
                                :a11y-label="'Edit or remove '.$item['name']"
                                class="h-12 w-12 items-center justify-center"
                                :menu="$this->itemMenu($item)"
                            >
                                <icon :ios="Ios::Ellipsis" :android="Android::MoreHoriz" :size="18" class="text-theme-on-surface-variant" />
                            </pressable>
                        </row>
                        @unless ($loop->last)
                            <divider class="ml-14" />
                        @endunless
                    @endforeach
                </column>
            @else
                <column ref="list-empty" class="w-full items-center gap-2 rounded-xl bg-theme-surface px-4 py-6">
                    <icon :ios="Ios::ListBullet" :android="Android::FormatListBulleted" :size="26" class="text-theme-primary" />
                    <text font="semibold" class="text-base text-theme-on-surface">This list is empty.</text>
                    <text class="text-sm text-theme-on-surface-variant">Add an item below.</text>
                </column>
            @endif
        </column>
    </scroll-view>
@else
    <x-ui.not-found ref="list-missing" thing="list" />
@endif
