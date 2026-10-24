@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- The row that shows where something lives and opens the destination
     picker. Expects $refPrefix and $selectedParent, and optionally $label
     and $noParentLabel;
     tapping calls the including component's openParentPicker() from
     ChoosesInventoryParent. --}}

<column class="w-full gap-2">
    <text font="semibold" class="text-lg text-theme-on-surface">{{ $label ?? 'Where does it go?' }}</text>
    <pressable
        ref="{{ $refPrefix }}-parent"
        :a11y-label="($label ?? 'Where does it go?').' '.($selectedParent['name'] ?? ($noParentLabel ?? 'Not in a place yet'))"
        a11y-hint="Choose where this lives"
        class="min-h-12 w-full justify-center rounded-xl border border-theme-outline bg-theme-surface py-2 pr-3 pl-4"
        @tap="openParentPicker"
    >
        <row class="w-full items-center gap-3">
            @if ($selectedParent)
                <x-ui.avatar :color="$selectedParent['type']->color()" :ios="$selectedParent['type']->iosIcon()" :android="$selectedParent['type']->androidIcon()" />
            @else
                <x-ui.avatar :ios="Ios::House" :android="Android::Home" />
            @endif
            <column class="flex-1 gap-0.5">
                <x-ui.heading size="lg">{{ $selectedParent['name'] ?? ($noParentLabel ?? 'Not in a place yet') }}</x-ui.heading>
                @if ($selectedParent['path'] ?? null)
                    <x-ui.text>in {{ $selectedParent['path'] }}</x-ui.text>
                @endif
            </column>
            <column class="min-h-10 justify-center rounded-full bg-theme-surface-variant px-4">
                <text font="semibold" class="text-base text-theme-on-surface">Change</text>
            </column>
        </row>
    </pressable>
</column>
