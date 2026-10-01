@use('App\Enums\RoutineFrequency')
@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- Shared by the create and edit screens. Expects $formRef, $submitLabel,
     $timeOfDayOptions, $frequencyOptions, $weekdayRows and $frequency, and
     on the edit screen $canDelete, $queuedChange and $deleting; the remaining state
     comes from the including component's ManagesRoutineForm properties.
     Computed values have to be passed in because an @include is not bound
     to $this. --}}

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        @if ($canDelete ?? false)
            @include('native.queued-change', ['refPrefix' => $formRef, 'queuedChange' => $queuedChange])
        @endif

        @if ($deleting ?? false)
            <text ref="{{ $formRef }}-deleting" class="text-sm text-theme-on-surface-variant">
                This routine is being deleted. Sunny will remove it the next time this phone syncs.
            </text>
        @endif

        <column class="w-full gap-4">
            <outlined-text-input
                ref="routine-name"
                label="Name"
                placeholder="Morning routine"
                autocapitalize="words"
                :ios-leading-icon="Ios::Tag"
                :android-leading-icon="Android::Label"
                native:model.blur="name"
            />

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Time of day</text>
                <button-group
                    ref="{{ $formRef }}-time-of-day"
                    :options="$timeOfDayOptions"
                    a11y-label="Time of day"
                    native:model="timeOfDayIndex"
                />
            </column>

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Repeats</text>
                <button-group
                    ref="{{ $formRef }}-frequency"
                    :options="$frequencyOptions"
                    a11y-label="Frequency"
                    native:model="frequencyIndex"
                />
            </column>

            @if ($frequency === RoutineFrequency::Weekly)
                <column class="w-full gap-2">
                    <text class="text-sm font-semibold text-theme-on-surface-variant">On these days</text>
                    @foreach ($weekdayRows as $weekdayRow)
                        <row class="w-full items-center justify-start gap-2">
                            @foreach ($weekdayRow as $weekday)
                                <chip
                                    ref="routine-weekday-{{ $weekday['day'] }}"
                                    :label="$weekday['name']"
                                    :selected="$weekday['selected']"
                                    @change="toggleWeekday({{ $weekday['day'] }})"
                                />
                            @endforeach
                        </row>
                    @endforeach
                </column>
            @elseif ($frequency === RoutineFrequency::Monthly)
                <outlined-text-input
                    ref="routine-day-of-month"
                    label="Day of the month"
                    placeholder="1–31"
                    keyboard="number"
                    native:model.blur="dayOfMonth"
                />
            @endif

            <toggle
                ref="routine-active"
                label="Active"
                a11y-label="Routine is active"
                native:model="isActive"
            />
        </column>

        <column class="w-full gap-2">
            <text class="text-sm font-semibold text-theme-on-surface-variant">Steps</text>

            @foreach ($steps as $index => $step)
                <row class="w-full items-center gap-1">
                    <outlined-text-input
                        ref="routine-step-name-{{ $index }}"
                        placeholder="Step {{ $index + 1 }}"
                        class="flex-1"
                        a11y-label="Step {{ $index + 1 }}"
                        :value="$step['name']"
                        sync-mode="blur"
                        @change="setStepName({{ $index }})"
                    />
                    <pressable
                        ref="routine-step-up-{{ $index }}"
                        a11y-label="Move step {{ $index + 1 }} up"
                        class="h-10 w-10 items-center justify-center"
                        @tap="moveStep({{ $index }}, -1)"
                    >
                        <icon :ios="Ios::ArrowUp" :android="Android::ArrowUpward" :size="18" class="text-theme-on-surface-variant" />
                    </pressable>
                    <pressable
                        ref="routine-step-down-{{ $index }}"
                        a11y-label="Move step {{ $index + 1 }} down"
                        class="h-10 w-10 items-center justify-center"
                        @tap="moveStep({{ $index }}, 1)"
                    >
                        <icon :ios="Ios::ArrowDown" :android="Android::ArrowDownward" :size="18" class="text-theme-on-surface-variant" />
                    </pressable>
                    <pressable
                        ref="routine-step-remove-{{ $index }}"
                        a11y-label="Remove step {{ $index + 1 }}"
                        class="h-10 w-10 items-center justify-center"
                        @tap="removeStep({{ $index }})"
                    >
                        <icon :ios="Ios::Xmark" :android="Android::Close" :size="18" class="text-theme-destructive" />
                    </pressable>
                </row>
            @endforeach

            <button ref="routine-add-step" variant="outline" class="w-full" @tap="addStep">
                Add step
            </button>
        </column>

        @if ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-sm text-theme-destructive">
                {{ $error }}
            </text>
        @endif

        <button
            ref="{{ $formRef }}-submit"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            :disabled="$saving || ($deleting ?? false)"
            @tap="save"
        >
            {{ $submitLabel }}
        </button>

        @if ($canDelete ?? false)
            <pressable
                ref="{{ $formRef }}-delete"
                class="h-12 items-center justify-center"
                :disabled="$deleting ?? false"
                @tap="confirmDelete"
            >
                <text class="text-base font-semibold text-theme-destructive">Delete routine</text>
            </pressable>
        @endif
    </column>
</scroll-view>
