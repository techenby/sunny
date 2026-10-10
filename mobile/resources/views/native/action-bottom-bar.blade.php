{{-- Labelled actions pinned above the tab bar, within thumb reach. Expects
     $actions, each with ref, label, ios, android and url. --}}

<native:bottom-bar>
    <row class="w-full items-center gap-3 px-4 pb-2">
        @foreach ($actions as $action)
            <pressable
                ref="{{ $action['ref'] }}"
                :a11y-label="$action['label']"
                class="h-12 flex-1 items-center justify-center rounded-full glass android:bg-theme-surface"
                @navigate($action['url'])
            >
                <row class="items-center gap-2">
                    <native:icon :ios="$action['ios']" :android="$action['android']" :size="20" class="text-theme-on-surface" />
                    <text font="semibold" class="text-base text-theme-on-surface">{{ $action['label'] }}</text>
                </row>
            </pressable>
        @endforeach
    </row>
</native:bottom-bar>
