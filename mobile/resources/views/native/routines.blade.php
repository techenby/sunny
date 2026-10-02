@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Routines" display-mode="large" :back="false">
    <native:top-bar-action
        ref="create-routine"
        id="create-routine"
        label="New routine"
        :ios-icon="Ios::Plus"
        :android-icon="Android::Add"
        @navigate('/routines/create')
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
                        <text font="semibold" class="text-xl text-theme-on-background">{{ $routine['name'] }}</text>
                        <text class="text-sm text-theme-on-surface-variant">{{ $routine['timeOfDay']->label() }} · {{ $routine['assignee'] }}</text>
                    </column>
                    @if ($routine['total'])
                        <text ref="routine-{{ $routine['id'] }}-progress" class="text-sm text-theme-on-surface-variant">{{ $routine['completed'] }} of {{ $routine['total'] }}</text>
                    @endif
                </row>

                <column class="w-full rounded-xl bg-theme-surface">
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
            <column ref="routines-empty" class="w-full items-center gap-2 rounded-xl bg-theme-surface px-4 py-6">
                <icon :ios="Ios::ChecklistChecked" :android="Android::Checklist" :size="28" class="text-theme-primary" />
                <text font="semibold" class="text-base text-theme-on-surface">No routines today</text>
                <text class="text-center text-sm text-theme-on-surface-variant">Routines due today will show up here. Pull down on the summary to refresh.</text>
            </column>
        @endforelse

        @if ($this->allRoutines)
            <column ref="all-routines" class="w-full gap-3">
                <text font="semibold" class="text-xl text-theme-on-background">All routines</text>

                <column class="w-full rounded-xl bg-theme-surface">
                    @foreach ($this->allRoutines as $routine)
                        <pressable
                            ref="edit-routine-{{ $routine['id'] }}"
                            :a11y-label="'Edit '.$routine['name']"
                            class="w-full px-4 py-3"
                            @navigate('/routines/'.$routine['id'].'/edit')
                        >
                            <row class="w-full items-center gap-3">
                                <icon :ios="$routine['timeOfDay']->iosIcon()" :android="$routine['timeOfDay']->androidIcon()" :size="20" class="text-theme-primary" />
                                <column class="flex-1 gap-0.5">
                                    <text class="text-base text-theme-on-surface">{{ $routine['name'] }}</text>
                                    <text class="text-sm text-theme-on-surface-variant">{{ $routine['summary'] }}</text>
                                </column>
                                <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="16" class="text-theme-outline" />
                            </row>
                        </pressable>
                        @unless ($loop->last)
                            <divider class="ml-12" />
                        @endunless
                    @endforeach
                </column>
            </column>
        @endif
    </column>
</scroll-view>
