@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($checklist = $this->checklist)

<native:top-bar :title="$checklist['name'] ?? 'List'" back>
    @if ($checklist)
        <native:top-bar-action
            ref="edit-list"
            id="edit-list"
            label="Edit"
            :ios-icon="Ios::Pencil"
            :android-icon="Android::Edit"
            @navigate('/lists/'.$checklist['id'].'/edit')
        />
        <native:top-bar-action
            ref="delete-list"
            id="delete-list"
            label="Delete"
            :ios-icon="Ios::Trash"
            :android-icon="Android::Delete"
            @tap="confirmDeleteList"
        />
    @endif
</native:top-bar>

@if ($checklist)
    <native:bottom-bar>
        <row class="w-full items-center gap-3 px-4 pb-2">
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
    </native:bottom-bar>

    <scroll-view ref="list-detail" fill class="bg-theme-background">
        <column class="w-full gap-4 px-4 pt-2 pb-8">
            <row class="w-full items-center gap-2">
                <icon :ios="$checklist['type']->iosIcon()" :android="$checklist['type']->androidIcon()" :size="16" class="text-theme-on-surface-variant" />
                <text ref="list-summary" class="flex-1 text-sm text-theme-on-surface-variant">{{ $this->summary }}</text>
            </row>

            @include('native.queued-change', ['refPrefix' => 'list', 'queuedChange' => $this->queuedChange])

            @if ($error !== '')
                <text ref="list-error" class="text-sm text-theme-destructive">{{ $error }}</text>
            @endif

            @if ($this->items)
                <column class="w-full rounded-xl bg-theme-surface">
                    @foreach ($this->items as $item)
                        <row class="w-full items-center">
                            <pressable
                                ref="list-item-{{ $item['id'] }}"
                                @press="toggleItem({{ $item['id'] }})"
                                :a11y-label="($item['completed'] ? 'Checked: ' : '').$item['name']"
                                class="flex-1 py-3 pl-4"
                            >
                                <row class="w-full items-center gap-3">
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
                            <pressable
                                ref="list-item-{{ $item['id'] }}-remove"
                                :a11y-label="'Remove '.$item['name']"
                                class="h-12 w-12 items-center justify-center"
                                @tap="removeItem({{ $item['id'] }})"
                            >
                                <icon :ios="Ios::Trash" :android="Android::Delete" :size="18" class="text-theme-on-surface-variant" />
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
    <column ref="list-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This list could not be found.
        </text>
    </column>
@endif
