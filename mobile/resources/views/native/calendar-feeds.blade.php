@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

@php($feeds = $this->feeds)

<native:top-bar title="Calendars" display-mode="large" back />

<native:bottom-bar>
    <row class="w-full items-center px-4 pb-2">
        <pressable ref="add-calendar" a11y-label="Add calendar" class="min-h-12 flex-1 items-center justify-center rounded-full bg-theme-primary" @navigate('/calendar/feeds/create')>
            <row class="items-center gap-2">
                <icon :ios="Ios::Plus" :android="Android::Add" :size="18" class="text-theme-on-primary" />
                <text font="semibold" class="text-lg text-theme-on-primary">Add calendar</text>
            </row>
        </pressable>
    </row>
</native:bottom-bar>

<refreshable ref="calendar-feeds" fill class="bg-theme-background" @refresh="refresh">
    <column class="w-full gap-3 px-4 pt-2 pb-8">
        <x-ui.text>Their events show on the team’s calendar. Anyone on the team can add, edit or remove them.</x-ui.text>

        @if ($status !== '')
            <column ref="calendar-feeds-problem" class="w-full gap-3 rounded-xl bg-theme-primary/15 px-4 py-4">
                <row class="w-full items-start gap-3">
                    <icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="20" class="text-theme-destructive" />
                    <column class="flex-1 gap-0.5">
                        <x-ui.heading class="text-theme-destructive">{{ $status === 'offline' ? 'You’re offline' : 'Sunny is unavailable' }}</x-ui.heading>
                        <text class="text-base text-theme-on-surface">{{ $feeds !== null ? 'These may be out of date. ' : '' }}Adding, editing or removing calendars needs a connection.</text>
                    </column>
                </row>
                <row class="w-full pl-8">
                    <pressable ref="calendar-feeds-retry" a11y-label="Try again" class="min-h-12 items-center justify-center rounded-full bg-theme-primary px-4" @tap="refresh">
                        <row class="items-center gap-1.5">
                            <icon :ios="Ios::ArrowClockwise" :android="Android::Refresh" :size="16" class="text-theme-on-primary" />
                            <text font="semibold" class="text-base text-theme-on-primary">Try again</text>
                        </row>
                    </pressable>
                </row>
            </column>
        @endif

        @if ($feeds === null)
            @if ($loading)
                <column ref="calendar-feeds-loading" class="w-full items-center gap-3 py-12">
                    <activity-indicator />
                    <x-ui.text size="sm">Loading calendars…</x-ui.text>
                </column>
            @endif
        @elseif (! $feeds['failing'] && ! $feeds['working'])
            <column ref="calendar-feeds-empty" class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6">
                <icon :ios="Ios::Calendar" :android="Android::CalendarMonth" :size="28" class="text-theme-primary" />
                <x-ui.heading>No calendars yet</x-ui.heading>
                <x-ui.text size="sm" class="text-center">Add Google Calendar, Proton or any calendar with an iCal link.</x-ui.text>
            </column>
        @else
            @foreach ($feeds['failing'] as $feed)
                <column ref="calendar-feed-{{ $feed['id'] }}-card" class="{{ Card::classes() }} w-full pb-3">
                    @include('native.calendar-feed-row', ['feed' => $feed])
                    <column ref="calendar-feed-{{ $feed['id'] }}-fix" class="mx-3 gap-1.5 rounded-xl bg-theme-primary/15 px-4 py-3">
                        <row class="w-full items-center gap-2">
                            <text font="accent" class="flex-1 text-sm text-theme-destructive">Sunny couldn’t fetch it</text>
                            @if ($feed['problem'] && str_starts_with($feed['problem'], 'HTTP '))
                                <text font="mono" class="rounded-md bg-theme-surface/70 px-1.5 py-0.5 text-xs text-theme-on-surface">{{ $feed['problem'] }}</text>
                            @endif
                        </row>
                        @if ($feed['problem'] && ! str_starts_with($feed['problem'], 'HTTP '))
                            <text ref="calendar-feed-{{ $feed['id'] }}-problem" font="mono" class="self-start rounded-md bg-theme-surface/70 px-1.5 py-0.5 text-xs text-theme-on-surface">{{ $feed['problem'] }}</text>
                        @endif
                        <text class="text-sm text-theme-on-surface">Check the link is right and the calendar is still shared. For Google Calendar, copy a fresh Secret address in iCal format and paste it in here.</text>
                    </column>
                </column>
            @endforeach

            @if ($feeds['working'])
                <column class="{{ Card::classes() }} w-full">
                    @foreach ($feeds['working'] as $feed)
                        @include('native.calendar-feed-row', ['feed' => $feed])
                        @unless ($loop->last)
                            <divider class="ml-[66]" />
                        @endunless
                    @endforeach
                </column>
            @endif
        @endif
    </column>
</refreshable>
