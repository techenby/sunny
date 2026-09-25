@use('App\Icons\Android')
@use('App\Icons\Ios')

<row class="w-full items-center gap-4 rounded-xl border border-theme-outline bg-theme-surface px-4 py-3 shadow-sm">
    <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
        <icon :ios="$iosIcon" :android="$androidIcon" class="text-theme-primary" :size="22" />
    </column>
    <column class="flex-1 gap-0.5">
        <text font="semibold" class="text-sm text-theme-on-surface">{{ $title }}</text>
        <text class="text-sm text-theme-on-surface-variant">{{ $description }}</text>
    </column>
    @if ($navigable)
        <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" class="text-theme-on-surface-variant" :size="16" />
    @endif
</row>
