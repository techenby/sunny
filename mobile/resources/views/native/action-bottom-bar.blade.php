{{-- Icon actions pinned above the tab bar, within thumb reach. Expects
     $actions, each with ref, label, ios, android and url; the label is
     read out by screen readers. --}}

<native:bottom-bar>
    <row class="w-full items-center justify-end gap-3 px-4 pb-2">
        @foreach ($actions as $action)
            <pressable
                ref="{{ $action['ref'] }}"
                :a11y-label="$action['label']"
                class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
                @navigate($action['url'])
            >
                <native:icon :ios="$action['ios']" :android="$action['android']" :size="22" class="text-theme-on-surface" />
            </pressable>
        @endforeach
    </row>
</native:bottom-bar>
