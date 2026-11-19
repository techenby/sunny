@use('App\Icons\Android')
@use('App\Icons\Ios')

<pressable
    ref="calendar-feed-{{ $feed['id'] }}"
    :a11y-label="$feed['name'].', '.$feed['detail']"
    :press-opacity="0.7"
    class="w-full px-4 py-3"
    @navigate('/calendar/feeds/'.$feed['id'].'/edit')
>
    <row class="w-full items-center gap-3.5">
        <column :class="'h-9 w-9 items-center justify-center rounded-full bg-'.$feed['color']">
            <icon :ios="Ios::Calendar" :android="Android::CalendarMonth" :size="18" class="text-white" />
        </column>
        <column class="flex-1 gap-0.5">
            <text class="text-lg text-theme-on-surface">{{ $feed['name'] }}</text>
            <text font="mono" max-lines="1" class="text-sm text-theme-on-surface-variant">{{ $feed['url'] }}</text>
            <row class="w-full items-center gap-1.5">
                @if ($feed['failing'])
                    <icon :ios="Ios::ExclamationmarkTriangleFill" :android="Android::Warning" :size="12" class="text-theme-destructive" />
                    <text font="semibold" class="flex-1 text-sm text-theme-destructive">{{ $feed['detail'] }}</text>
                @else
                    <column class="h-2 w-2 rounded-full bg-green-600" />
                    <text class="flex-1 text-sm text-theme-on-surface-variant">{{ $feed['detail'] }}</text>
                @endif
            </row>
        </column>
        <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
    </row>
</pressable>
