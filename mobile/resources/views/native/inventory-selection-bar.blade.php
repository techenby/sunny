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
                <x-ui.button
                    ref="selection-all"
                    variant="plain"
                    class="min-h-11"
                    max-lines="1"
                    :a11y-label="$selectedAll ? 'Deselect all' : 'Select all'"
                    tap="toggleSelectAll"
                >{{ $selectedAll ? 'Deselect all' : 'Select all' }}</x-ui.button>
            </row>
            <column class="w-full gap-2.5 {{ $count === 0 ? 'opacity-40' : '' }}">
                <x-ui.button
                    ref="selection-move"
                    variant="filled"
                    size="lg"
                    class="w-full"
                    :ios="Ios::ArrowRight"
                    :android="Android::ArrowForward"
                    a11y-label="Move"
                    a11y-hint="Choose a new place for what you picked"
                    tap="startMoveSelected"
                >Move</x-ui.button>
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
