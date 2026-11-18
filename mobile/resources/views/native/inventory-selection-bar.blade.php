@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($count = count($selectedItems))

<native:bottom-bar>
    <column class="w-full px-4 pb-2">
        <column class="w-full gap-2.5 rounded-[28] p-3 glass android:bg-theme-surface">
            <row class="w-full items-center gap-3 px-1.5">
                <text ref="selection-count" font="semibold" max-lines="1" class="flex-1 text-base text-theme-on-surface">
                    {{ trans_choice('{0} Nothing picked yet|{1} :count thing picked|[2,*] :count things picked', $count) }}
                </text>
                <pressable
                    ref="selection-all"
                    a11y-label="{{ $selectedAll ? 'Deselect all' : 'Select all' }}"
                    class="min-h-11 justify-center"
                    @tap="toggleSelectAll"
                >
                    <text font="semibold" max-lines="1" class="text-base text-theme-primary-text">{{ $selectedAll ? 'Deselect all' : 'Select all' }}</text>
                </pressable>
            </row>
            <column class="w-full gap-2.5 {{ $count === 0 ? 'opacity-40' : '' }}">
                <pressable
                    ref="selection-move"
                    a11y-label="Move"
                    a11y-hint="Choose a new place for what you picked"
                    class="min-h-12 w-full items-center justify-center rounded-full bg-theme-primary px-4"
                    @tap="startMoveSelected"
                >
                    <row class="items-center gap-2">
                        <native:icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="18" class="text-theme-on-primary" />
                        <text font="semibold" class="text-lg text-theme-on-primary">Move</text>
                    </row>
                </pressable>
                <pressable
                    ref="selection-delete"
                    a11y-label="Delete"
                    class="min-h-12 w-full items-center justify-center rounded-full bg-theme-destructive/10 px-4"
                    @tap="confirmDeleteSelected"
                >
                    <row class="items-center gap-2">
                        <native:icon :ios="Ios::Trash" :android="Android::Delete" :size="18" class="text-theme-destructive" />
                        <text font="semibold" class="text-lg text-theme-destructive">Delete</text>
                    </row>
                </pressable>
            </column>
        </column>
    </column>
</native:bottom-bar>
