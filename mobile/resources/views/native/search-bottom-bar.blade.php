@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- A search field with a create button beside it, pinned above the
     keyboard. Expects $refPrefix, $placeholder, $search, $createLabel and
     $createUrl, and optionally $scanLabel and $scanUrl for a scan button;
     typing calls the including screen's updateSearch(). The top bar's
     native search can't hold extra buttons, so this replaces it. --}}

<native:bottom-bar>
    <row class="w-full items-center gap-3 px-4 pb-2">
        <row class="h-12 flex-1 items-center gap-2 rounded-full px-4 glass android:bg-theme-surface">
            <native:icon :ios="Ios::Magnifyingglass" :android="Android::Search" :size="18" class="text-theme-on-surface-variant" />
            <bare-text-input
                ref="{{ $refPrefix }}-search"
                class="flex-1"
                :placeholder="$placeholder"
                a11y-label="{{ $placeholder }}"
                :value="$search"
                sync-mode="debounce"
                debounce-ms="300"
                @change="updateSearch"
            />
        </row>
        @isset($scanUrl)
            <pressable
                ref="{{ $refPrefix }}-scan"
                a11y-label="{{ $scanLabel }}"
                class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
                @navigate($scanUrl)
            >
                <native:icon :ios="Ios::CameraViewfinder" :android="Android::DocumentScanner" :size="22" class="text-theme-on-surface" />
            </pressable>
        @endisset
        <pressable
            ref="{{ $refPrefix }}-create"
            a11y-label="{{ $createLabel }}"
            class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
            @navigate($createUrl)
        >
            <native:icon :ios="Ios::Plus" :android="Android::Add" :size="22" class="text-theme-on-surface" />
        </pressable>
    </row>
</native:bottom-bar>
