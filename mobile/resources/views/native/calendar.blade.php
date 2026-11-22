@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Support\CalendarAgenda')
@use('App\Ui\Card')

@php($snapshot = $this->calendarSnapshot)
@php($feedColors = collect($this->calendarFeeds['feeds'] ?? [])->pluck('color', 'id'))

<native:top-bar title="Calendar" back />

<native:bottom-bar>
    <row class="w-full items-center justify-end gap-3 px-4 pb-2">
        @if ($this->hasNoCalendars)
            <x-ui.button
                ref="add-first-calendar"
                class="flex-1"
                :ios="Ios::Plus"
                :android="Android::Add"
                a11y-label="Add a calendar"
                navigate="/calendar/feeds/create"
            >Add a calendar</x-ui.button>
        @endif
        <x-ui.button
            ref="calendar-more"
            variant="glass"
            :ios="Ios::Ellipsis"
            :android="Android::MoreHoriz"
            :a11y-label="$snapshot && $snapshot['warnings'] ? 'More, a calendar isn’t showing' : 'More'"
            :menu="$this->moreMenu()"
        />
    </row>
</native:bottom-bar>

<refreshable ref="calendar" fill class="bg-theme-background" @refresh="refresh">
    @if ($this->hasNoCalendars)
        <column class="w-full px-4 pt-2 pb-8">
            <x-ui.empty-state
                ref="calendar-empty"
                :ios="Ios::Calendar"
                :android="Android::CalendarMonth"
                heading="No calendars yet"
                text="Connect Google Calendar, Proton or any calendar with an iCal link to see the next six weeks of plans here."
            />
        </column>
    @else
        <column class="w-full gap-5 px-4 pt-2 pb-8">
            @if ($calendarStatus !== '')
                <column ref="calendar-problem" class="w-full gap-3 rounded-xl bg-theme-primary/15 px-4 py-4">
                    <row class="w-full items-start gap-3">
                        <icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="20" class="text-theme-destructive" />
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading class="text-theme-destructive">{{ $calendarStatus === 'offline' ? 'You’re offline' : 'Sunny is unavailable' }}</x-ui.heading>
                            @if ($this->calendarUpdatedAt)
                                <text class="text-base text-theme-on-surface">These are the events from {{ $this->calendarUpdatedAt }}, so they may be out of date.</text>
                            @elseif ($calendarStatus === 'offline')
                                <text class="text-base text-theme-on-surface">Events come live from your calendars. Connect to see them.</text>
                            @else
                                <text class="text-base text-theme-on-surface">Sunny couldn’t load your events. Try again in a moment.</text>
                            @endif
                        </column>
                    </row>
                    <row class="w-full pl-8">
                        <x-ui.button
                            ref="calendar-retry"
                            :ios="Ios::ArrowClockwise"
                            :android="Android::Refresh"
                            a11y-label="Try again"
                            tap="refresh"
                        >Try again</x-ui.button>
                    </row>
                </column>
            @elseif ($this->updatedLabel)
                <row ref="calendar-updated" class="w-full items-center justify-center gap-1.5">
                    <icon :ios="Ios::ArrowClockwise" :android="Android::Refresh" :size="12" class="text-theme-on-surface-variant" />
                    <x-ui.text size="sm">{{ $this->updatedLabel }}</x-ui.text>
                </row>
            @endif

            @foreach ($snapshot['warnings'] ?? [] as $warning)
                <column ref="calendar-warning-{{ $warning['feed_id'] }}" class="{{ Card::classes() }} w-full gap-3 px-4 py-4">
                    <row class="w-full items-start gap-3">
                        <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-destructive/10">
                            <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="18" class="text-theme-destructive" />
                        </column>
                        <column class="flex-1 gap-1">
                            <row class="w-full items-center gap-2">
                                @if ($feedColors->has($warning['feed_id']))
                                    <column :class="'h-2.5 w-2.5 rounded-full bg-'.CalendarAgenda::color($feedColors[$warning['feed_id']])" />
                                @endif
                                <x-ui.heading class="flex-1">{{ $warning['feed_name'] }} isn’t showing</x-ui.heading>
                            </row>
                            <x-ui.text size="sm">We couldn’t fetch this calendar, so its events are missing. Its link may have expired.</x-ui.text>
                            @if ($problem = CalendarAgenda::problem($warning['message']))
                                <x-ui.badge size="sm" class="mt-1 self-start">{{ $problem }}</x-ui.badge>
                            @endif
                        </column>
                    </row>
                    <x-ui.button
                        ref="calendar-warning-{{ $warning['feed_id'] }}-fix"
                        class="w-full"
                        :ios="Ios::ArrowRight"
                        :android="Android::ArrowForward"
                        icon-trailing
                        a11y-label="Fix it in Calendars"
                        navigate="/calendar/feeds"
                    >Fix it in Calendars</x-ui.button>
                </column>
            @endforeach

            @if ($snapshot)
                @forelse ($this->days as $day)
                    <column ref="calendar-day-{{ $day['key'] }}" class="w-full gap-2">
                        <row class="w-full items-center gap-2 px-1">
                            <text font="semibold" class="text-lg text-theme-on-background">{{ $day['name'] }}</text>
                            <text class="flex-1 text-sm text-theme-on-surface-variant">{{ $day['detail'] }}</text>
                        </row>
                        <column class="{{ Card::classes() }} w-full py-1">
                            @foreach ($day['events'] as $event)
                                @include('native.calendar-event', ['event' => $event, 'ref' => 'calendar-'.$day['key'].'-'.$loop->index])
                                @unless ($loop->last)
                                    <divider class="ml-4" />
                                @endunless
                            @endforeach
                        </column>
                    </column>
                @empty
                    <x-ui.empty-state
                        ref="calendar-nothing"
                        :ios="Ios::SunMax"
                        :android="Android::WbSunny"
                        heading="Nothing in the next six weeks"
                        text="Events from your calendars will show up here."
                    />
                @endforelse
            @elseif ($this->calendarIsLoading)
                <column ref="calendar-loading" class="w-full items-center gap-3 py-12">
                    <activity-indicator />
                    <x-ui.text size="sm">Loading events…</x-ui.text>
                </column>
            @endif
        </column>
    @endif
</refreshable>
