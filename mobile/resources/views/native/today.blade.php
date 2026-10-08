@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Routines" display-mode="large" :back="false">
    <native:top-bar-action
        ref="all-routines"
        id="all-routines"
        label="All routines"
        :ios-icon="Ios::ListBullet"
        :android-icon="Android::FormatListBulleted"
        @navigate('/routines')
    />
</native:top-bar>

<scroll-view ref="routines" fill class="bg-theme-background">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        @if ($this->heading)
            <text ref="routines-date" class="text-base text-theme-on-surface-variant">{{ $this->heading }}</text>
        @endif

        @forelse ($this->routines as $routine)
            <column ref="routine-{{ $routine['id'] }}" class="w-full gap-3">
                <row class="w-full items-center gap-3">
                    <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary">
                        <icon :ios="$routine['timeOfDay']->iosIcon()" :android="$routine['timeOfDay']->androidIcon()" :size="18" class="text-theme-on-primary" />
                    </column>
                    <column class="flex-1 gap-0.5">
                        <x-ui.heading size="lg">{{ $routine['name'] }}</x-ui.heading>
                        <x-ui.text size="sm">{{ $routine['timeOfDay']->label() }} · {{ $routine['assignee'] }}</x-ui.text>
                    </column>
                    @if ($routine['total'])
                        <text ref="routine-{{ $routine['id'] }}-progress" class="text-sm text-theme-on-surface-variant">{{ $routine['completed'] }} of {{ $routine['total'] }}</text>
                    @endif
                </row>

                <column class="{{ Card::classes() }} w-full">
                    @forelse ($routine['steps'] as $step)
                        <pressable
                            ref="routine-step-{{ $step['id'] }}"
                            @press="toggleStep({{ $step['id'] }})"
                            :a11y-label="($step['completed'] ? 'Done: ' : '').$step['name']"
                            class="w-full px-4 py-3"
                        >
                            <row class="w-full items-center gap-3">
                                <icon
                                    :ios="$step['completed'] ? Ios::CheckmarkCircleFill : Ios::Circle"
                                    :android="$step['completed'] ? Android::CheckCircle : Android::RadioButtonUnchecked"
                                    :size="22"
                                    :class="$step['completed'] ? 'text-theme-primary' : 'text-theme-outline'"
                                />
                                <text :class="$step['completed'] ? 'flex-1 text-base text-theme-on-surface-variant line-through' : 'flex-1 text-base text-theme-on-surface'">{{ $step['name'] }}</text>
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
                <x-ui.heading>No routines today</x-ui.heading>
                <x-ui.text size="sm" class="text-center">Routines due today will show up here. Pull down on the summary to refresh.</x-ui.text>
            </column>
        @endforelse
    </column>
</scroll-view>
