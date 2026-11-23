@use('App\Icons\Android')
@use('App\Icons\Ios')

<row class="w-full items-center pr-1">
    <pressable
        ref="calendar-feed-{{ $feed['id'] }}"
        :a11y-label="implode(', ', array_filter([$feed['name'], $feed['detail']]))"
        :press-opacity="0.7"
        class="flex-1 py-3 pl-4"
        @navigate('/calendar/feeds/'.$feed['id'].'/edit')
    >
        <row class="w-full items-center gap-3.5">
            <column :class="'h-9 w-9 items-center justify-center rounded-full bg-'.$feed['color']">
                <icon :ios="Ios::Calendar" :android="Android::CalendarMonth" :size="18" class="text-white" />
            </column>
            <column class="flex-1 gap-0.5">
                <text class="text-lg text-theme-on-surface">{{ $feed['name'] }}</text>
                <text font="mono" max-lines="1" class="text-sm text-theme-on-surface-variant">{{ $feed['url'] }}</text>
                @if ($feed['failing'])
                    <row class="w-full items-center gap-1.5">
                        <icon :ios="Ios::ExclamationmarkTriangleFill" :android="Android::Warning" :size="12" class="text-theme-destructive" />
                        <text font="semibold" class="flex-1 text-sm text-theme-destructive">{{ $feed['detail'] }}</text>
                    </row>
                @elseif ($feed['detail'])
                    <text class="text-sm text-theme-on-surface-variant">{{ $feed['detail'] }}</text>
                @endif
            </column>
        </row>
    </pressable>
    <x-ui.button
        ref="calendar-feed-{{ $feed['id'] }}-more"
        variant="text"
        :ios="Ios::Ellipsis"
        :android="Android::MoreHoriz"
        :a11y-label="'More for '.$feed['name']"
        :menu="$menu"
    />
</row>
