@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($routine = $this->routine)

<native:top-bar :title="$routine['name'] ?? 'Routine'" back>
    @if ($routine)
        <native:top-bar-action
            ref="edit-routine"
            id="edit-routine"
            label="Edit"
            :ios-icon="Ios::Pencil"
            :android-icon="Android::Edit"
            @navigate('/routines/'.$routine['id'].'/edit')
        />
        <native:top-bar-action
            ref="delete-routine"
            id="delete-routine"
            label="Delete"
            :ios-icon="Ios::Trash"
            :android-icon="Android::Delete"
            @tap="confirmDeleteRoutine"
        />
    @endif
</native:top-bar>

@if ($routine)
    <native:bottom-bar>
        <row class="w-full items-center gap-3 px-4 pb-2">
            <row class="h-12 flex-1 items-center gap-2 rounded-full px-4 glass android:bg-theme-surface">
                <bare-text-input
                    ref="routine-new-step"
                    class="flex-1"
                    placeholder="Add a step"
                    a11y-label="Add a step"
                    autocapitalize="sentences"
                    keep-focus-on-submit
                    native:model="newStep"
                    @submit="addStep"
                />
            </row>
            <pressable
                ref="routine-add-step"
                a11y-label="Add step"
                class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
                @tap="addStep"
            >
                <native:icon :ios="Ios::Plus" :android="Android::Add" :size="22" class="text-theme-on-surface" />
            </pressable>
        </row>
    </native:bottom-bar>

    <scroll-view ref="routine-detail" fill class="bg-theme-background">
        <column class="w-full gap-4 px-4 pt-2 pb-8">
            <row class="w-full items-center gap-2">
                <icon :ios="$routine['time_of_day']->iosIcon()" :android="$routine['time_of_day']->androidIcon()" :size="16" class="text-theme-on-surface-variant" />
                <text ref="routine-summary" class="flex-1 text-sm text-theme-on-surface-variant">{{ $routine['summary'] }}</text>
            </row>

            @include('native.queued-change', ['refPrefix' => 'routine', 'queuedChange' => $this->queuedChange])

            @if ($error !== '')
                <text ref="routine-error" class="text-sm text-theme-destructive">{{ $error }}</text>
            @endif

            @if ($this->steps)
                <column class="w-full rounded-xl bg-theme-surface">
                    @foreach ($this->steps as $step)
                        <row ref="routine-step-{{ $step['id'] }}" class="w-full items-center">
                            <row class="flex-1 items-center gap-3 py-3 pl-4">
                                <text class="w-6 text-center text-sm text-theme-on-surface-variant">{{ $loop->iteration }}</text>
                                <column class="flex-1 gap-0.5">
                                    <text class="text-base text-theme-on-surface">{{ $step['name'] }}</text>
                                    @if ($step['error'])
                                        <text ref="routine-step-{{ $step['id'] }}-error" class="text-sm text-theme-destructive">Not saved to Sunny. {{ $step['error'] }}</text>
                                    @endif
                                </column>
                            </row>
                            <pressable
                                ref="routine-step-{{ $step['id'] }}-remove"
                                :a11y-label="'Remove '.$step['name']"
                                class="h-12 w-12 items-center justify-center"
                                @tap="removeStep({{ $step['id'] }})"
                            >
                                <icon :ios="Ios::Trash" :android="Android::Delete" :size="18" class="text-theme-on-surface-variant" />
                            </pressable>
                        </row>
                        @unless ($loop->last)
                            <divider class="ml-14" />
                        @endunless
                    @endforeach
                </column>
            @else
                <column ref="routine-empty" class="w-full items-center gap-2 rounded-xl bg-theme-surface px-4 py-6">
                    <icon :ios="Ios::ChecklistChecked" :android="Android::Checklist" :size="26" class="text-theme-primary" />
                    <text font="semibold" class="text-base text-theme-on-surface">This routine has no steps.</text>
                    <text class="text-sm text-theme-on-surface-variant">Add a step below.</text>
                </column>
            @endif
        </column>
    </scroll-view>
@else
    <column ref="routine-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This routine could not be found.
        </text>
    </column>
@endif
