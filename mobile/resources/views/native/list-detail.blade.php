@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

@php($checklist = $this->checklist)

<native:top-bar :title="$checklist['name'] ?? 'List'" display-mode="large" back />

@if ($checklist)
    @php($editingItem = $editingItemId !== null ? collect($this->items)->firstWhere('id', $editingItemId) : null)

    <native:bottom-bar>
        @if ($editingItem)
            @php($showsLinkField = $this->takesLinks || $editingItem['url'] !== null)
            <column class="w-full px-3 pb-2">
                <column ref="list-edit-item" class="w-full gap-2.5 rounded-[24] p-3 glass android:bg-theme-surface">
                    <row class="w-full items-center">
                        <text class="flex-1 text-sm text-theme-on-surface-variant">Edit item</text>
                        <x-ui.button
                            ref="list-edit-cancel"
                            variant="text"
                            class="pl-3"
                            a11y-label="Cancel"
                            tap="cancelEdit"
                        >Cancel</x-ui.button>
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
                                    <x-ui.button
                                        ref="list-edit-url-clear"
                                        variant="text"
                                        :ios="Ios::XmarkCircleFill"
                                        :android="Android::Cancel"
                                        a11y-label="Clear link"
                                        tap="clearEditingUrl"
                                    />
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
                            <x-ui.button
                                ref="list-edit-remove-link"
                                variant="text"
                                :ios="Ios::Xmark"
                                :android="Android::LinkOff"
                                a11y-label="Remove link"
                                tap="removeLink"
                            >Remove link</x-ui.button>
                        @endif
                        <column class="flex-1" />
                        <x-ui.button
                            ref="list-edit-save"
                            class="shrink-0"
                            a11y-label="Save"
                            tap="saveItem"
                        >Save</x-ui.button>
                    </row>
                </column>
            </column>
        @elseif ($this->takesLinks)
            <column class="w-full px-3 pb-2">
                <column ref="list-new-item-card" class="w-full gap-1.5 rounded-[24] p-2 glass android:bg-theme-surface">
                    <row class="min-h-10 w-full items-center pl-3">
                        <bare-text-input
                            ref="list-new-item"
                            class="flex-1"
                            placeholder="Add an item"
                            a11y-label="Add an item"
                            autocapitalize="sentences"
                            next-focus="list-new-item-url"
                            native:model="newItem"
                        />
                        <x-ui.button ref="list-add-item" variant="text" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add an item" tap="addItem" />
                    </row>
                    <row class="w-full items-center gap-2">
                        <row class="min-h-12 flex-1 items-center gap-2 rounded-full border bg-theme-surface pr-1 pl-3 {{ $linkError !== '' ? 'border-theme-primary' : 'border-theme-outline' }}">
                            <icon :ios="Ios::Link" :android="Android::Link" :size="16" class="text-theme-primary" />
                            <bare-text-input
                                ref="list-new-item-url"
                                class="flex-1"
                                placeholder="Link"
                                a11y-label="Link"
                                keyboard="url"
                                next-focus="list-new-item"
                                native:model="newItemUrl"
                                @submit="submitNewItemUrl"
                            />
                            @if ($newItemUrl !== '')
                                <x-ui.button
                                    ref="list-new-item-url-clear"
                                    variant="text"
                                    :ios="Ios::XmarkCircleFill"
                                    :android="Android::Cancel"
                                    a11y-label="Clear link"
                                    tap="clearNewItemUrl"
                                />
                            @endif
                        </row>
                        <x-ui.button ref="list-more" variant="glass" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" :menu="$this->moreMenu()" />
                    </row>
                    @if ($linkError !== '')
                        @include('native.list-link-error')
                    @else
                        <text class="px-3 pb-1 text-xs text-theme-on-surface-variant">Link is optional. Type it, or paste one in.</text>
                    @endif
                </column>
            </column>
        @else
            <row class="w-full items-center gap-3 px-4 pb-2">
                <row class="h-12 flex-1 items-center gap-2 rounded-full pr-1 pl-4 glass android:bg-theme-surface">
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
                    <x-ui.button ref="list-add-item" variant="text" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add an item" tap="addItem" />
                </row>
                <x-ui.button ref="list-more" variant="glass" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" :menu="$this->moreMenu()" />
            </row>
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
                <column class="w-full {{ Card::classes() }}">
                    @foreach ($this->items as $item)
                        <row class="w-full items-center {{ $editingItemId === $item['id'] ? 'bg-theme-primary/10' : '' }} {{ $loop->first ? 'rounded-t-[17]' : '' }} {{ $loop->last ? 'rounded-b-[17]' : '' }}">
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
                            <x-ui.button
                                ref="list-item-{{ $item['id'] }}-actions"
                                variant="text"
                                :ios="Ios::Ellipsis"
                                :android="Android::MoreHoriz"
                                :a11y-label="'Edit or delete '.$item['name']"
                                :menu="$this->itemMenu($item)"
                            />
                        </row>
                        @unless ($loop->last)
                            <divider class="ml-[50]" />
                        @endunless
                    @endforeach
                </column>
            @else
                <x-ui.empty-state
                    ref="list-empty"
                    :ios="Ios::ListBullet"
                    :android="Android::FormatListBulleted"
                    heading="This list is empty."
                    text="Add an item below."
                />
            @endif
        </column>
    </scroll-view>
@else
    <x-ui.not-found ref="list-missing" thing="list" />
@endif
