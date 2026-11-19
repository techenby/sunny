@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar :title="$this->labels['title']" display-mode="large" :back="false">
    <native:top-bar-action
        ref="all-routines"
        id="all-routines"
        label="All routines"
        :ios-icon="Ios::ListBullet"
        :android-icon="Android::FormatListBulleted"
        @navigate('/routines')
    />
</native:top-bar>

<native:bottom-bar>
    <row ref="day-bar" class="w-full items-center gap-3 px-4 pb-2">
        @if ($this->canGoBack)
            <pressable
                ref="previous-day"
                a11y-label="Previous day"
                class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
                @tap="previousDay"
            >
                <native:icon :ios="Ios::ChevronLeft" :android="Android::ChevronLeft" :size="22" class="text-theme-on-surface" />
            </pressable>
        @else
            <column ref="previous-day" class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface opacity-40">
                <native:icon :ios="Ios::ChevronLeft" :android="Android::ChevronLeft" :size="22" class="text-theme-on-surface" />
            </column>
        @endif
        @if ($this->isToday)
            <column ref="back-to-today" class="min-h-12 flex-1 items-center justify-center rounded-full px-4 glass android:bg-theme-surface opacity-40">
                <text font="semibold" class="text-lg text-theme-on-surface">Today</text>
            </column>
        @else
            <pressable
                ref="back-to-today"
                a11y-label="Back to today"
                class="min-h-12 flex-1 items-center justify-center rounded-full px-4 glass android:bg-theme-surface"
                @tap="showToday"
            >
                <text font="semibold" class="text-lg text-theme-on-surface">Today</text>
            </pressable>
        @endif
        @if ($this->canGoForward)
            <pressable
                ref="next-day"
                a11y-label="Next day"
                class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
                @tap="nextDay"
            >
                <native:icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="22" class="text-theme-on-surface" />
            </pressable>
        @else
            <column ref="next-day" class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface opacity-40">
                <native:icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="22" class="text-theme-on-surface" />
            </column>
        @endif
    </row>
</native:bottom-bar>

<scroll-view ref="routines" fill class="bg-theme-background">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        @if ($this->labels['date'])
            <text ref="routines-date" class="text-base text-theme-on-surface-variant">{{ $this->labels['date'] }}</text>
        @endif

        @if ($loadError)
            <column ref="day-unavailable" class="w-full gap-4 rounded-xl bg-theme-destructive/10 px-4 py-4">
                <row class="w-full items-start gap-3">
                    <icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="22" class="text-theme-destructive" />
                    <column class="flex-1 gap-0.5">
                        @if ($loadError === 'offline')
                            <x-ui.heading class="text-theme-destructive">You’re offline</x-ui.heading>
                            <x-ui.text>Your phone only keeps today and tomorrow. Connect to see {{ $this->labels['possessive'] }} routines and tick things off.</x-ui.text>
                        @else
                            <x-ui.heading class="text-theme-destructive">Sunny is unavailable</x-ui.heading>
                            <x-ui.text>Sunny couldn’t send {{ $this->labels['possessive'] }} routines. Try again in a moment.</x-ui.text>
                        @endif
                    </column>
                </row>
                <row class="w-full items-center gap-4 pl-9">
                    <pressable ref="day-retry" a11y-label="Try again" class="min-h-12 items-center justify-center rounded-full bg-theme-surface px-4" @tap="retry">
                        <row class="items-center gap-1.5">
                            <icon :ios="Ios::ArrowClockwise" :android="Android::Refresh" :size="16" class="text-theme-on-surface" />
                            <text font="semibold" class="text-base text-theme-on-surface">Try again</text>
                        </row>
                    </pressable>
                    <pressable ref="day-back-to-today" a11y-label="Back to today" class="min-h-12 justify-center" @tap="showToday">
                        <text font="semibold" class="text-base text-theme-primary-text">Back to today</text>
                    </pressable>
                </row>
            </column>
        @elseif ($loading)
            <column ref="day-loading" class="w-full items-center gap-3 py-6">
                <activity-indicator />
                <x-ui.text size="sm">Loading {{ $this->labels['possessive'] }} routines…</x-ui.text>
            </column>
        @else
            @forelse ($this->routines as $routine)
                <column ref="routine-{{ $routine['id'] }}" class="w-full gap-3">
                    <row class="w-full items-start gap-3">
                        <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary">
                            <icon :ios="$routine['timeOfDay']->iosIcon()" :android="$routine['timeOfDay']->androidIcon()" :size="18" class="text-theme-on-primary" />
                        </column>
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="lg">{{ $routine['name'] }}</x-ui.heading>
                            <x-ui.text ref="routine-{{ $routine['id'] }}-subtitle" size="sm">{{ $routine['timeOfDay']->label() }} · {{ $routine['assignee'] }}{{ $routine['total'] ? ' · '.$routine['completed'].' of '.$routine['total'] : '' }}</x-ui.text>
                        </column>
                    </row>

                    <column class="{{ Card::classes() }} w-full">
                        @forelse ($routine['steps'] as $step)
                            <pressable
                                ref="routine-step-{{ $step['id'] }}"
                                @press="toggleStep({{ $step['id'] }})"
                                :a11y-label="($step['completed'] ? 'Done: ' : '').$step['name']"
                                class="w-full px-4 py-3"
                            >
                                <row class="w-full items-start gap-3">
                                    <icon
                                        :ios="$step['completed'] ? Ios::CheckmarkCircleFill : Ios::Circle"
                                        :android="$step['completed'] ? Android::CheckCircle : Android::RadioButtonUnchecked"
                                        :size="22"
                                        :class="$step['completed'] ? 'text-theme-primary' : 'text-theme-outline'"
                                    />
                                    <text :class="$step['completed'] ? 'flex-1 text-base text-theme-on-surface-variant line-through' : 'flex-1 text-base text-theme-on-surface'">{{ $step['name'] }}</text>
                                    @if ($step['completed'] && $justTicked === $step['id'])
                                        <text ref="routine-step-{{ $step['id'] }}-just-ticked" font="medium" class="rounded-md bg-theme-primary/15 px-2 py-1 text-xs text-theme-primary-text">Ticked off just now</text>
                                    @endif
                                </row>
                            </pressable>
                            @unless ($loop->last)
                                <divider class="ml-14" />
                            @endunless
                        @empty
                            <text class="w-full px-4 py-3 text-sm text-theme-on-surface-variant">No steps yet.</text>
                        @endforelse
                    </column>
                </column>
            @empty
                <column ref="routines-empty" class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6">
                    <icon :ios="Ios::ChecklistChecked" :android="Android::Checklist" :size="28" class="text-theme-primary" />
                    <x-ui.heading>{{ $this->labels['empty'] }}</x-ui.heading>
                    <x-ui.text size="sm" class="text-center">{{ $this->labels['emptyDetail'] }}</x-ui.text>
                </column>
            @endforelse
        @endif
    </column>
</scroll-view>
