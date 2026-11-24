@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- A search field pinned above the keyboard, with an optional create
     button beside it. Expects $refPrefix, $placeholder and $search, and
     optionally $createLabel and $createUrl; typing calls the including
     screen's updateSearch(). The top bar's native search can't hold extra
     buttons, so this replaces it. --}}

<native:bottom-bar>
    <row class="w-full items-center gap-3 px-4 pb-2">
        <row class="min-h-12 flex-1 items-center gap-2 rounded-full px-4 glass android:bg-theme-surface">
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
        @isset($createUrl)
            <x-ui.button
                ref="{{ $refPrefix }}-create"
                variant="glass"
                :ios="Ios::Plus"
                :android="Android::Add"
                :a11y-label="$createLabel"
                :navigate="$createUrl"
            />
        @endisset
    </row>
</native:bottom-bar>
