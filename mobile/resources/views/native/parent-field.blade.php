@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- The row that shows where something lives and opens the destination
     picker. Expects $refPrefix and $selectedParent, and optionally $label;
     tapping calls the including component's openParentPicker() from
     ChoosesInventoryParent. --}}

<column class="w-full gap-2">
    <text class="text-sm font-semibold text-theme-on-surface-variant">{{ $label ?? 'Inside' }}</text>
    <pressable
        ref="{{ $refPrefix }}-parent"
        :a11y-label="($label ?? 'Inside').' '.($selectedParent['name'] ?? 'Top level')"
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
