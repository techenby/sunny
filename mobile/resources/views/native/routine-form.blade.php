@use('App\Enums\RoutineFrequency')
@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($frequency = RoutineFrequency::cases()[$frequencyIndex] ?? RoutineFrequency::Daily)

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        <column class="w-full gap-4">
            <outlined-text-input
                ref="{{ $formRef }}-name"
                label="Name"
                placeholder="Morning routine"
                autocapitalize="sentences"
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
                    a11y-label="Repeats"
                    native:model="frequencyIndex"
                />
            </column>

            @if ($frequency === RoutineFrequency::Weekly)
                <column class="w-full gap-2">
                    <text class="text-sm font-semibold text-theme-on-surface-variant">On these days</text>
                    @foreach ($weekdayRows as $weekdayRow)
                        <row class="w-full items-center justify-start gap-2">
                            @foreach ($weekdayRow as $number => $day)
                                <chip
                                    ref="{{ $formRef }}-weekday-{{ $number }}"
                                    :label="$day"
                                    :selected="in_array($number, $weekdays, true)"
                                    @change="toggleWeekday({{ $number }})"
                                />
                            @endforeach
                        </row>
                    @endforeach
                </column>
            @endif

            @if ($frequency === RoutineFrequency::Monthly)
                <outlined-text-input
                    ref="{{ $formRef }}-day-of-month"
                    label="Day of the month"
                    placeholder="1"
                    keyboard="number"
                    supporting="Routines set past the end of a short month run on its last day."
                    :ios-leading-icon="Ios::Calendar"
                    :android-leading-icon="Android::CalendarMonth"
                    native:model.blur="dayOfMonth"
                />
            @endif

            <toggle
                ref="{{ $formRef }}-active"
                label="Active"
                a11y-hint="Paused routines stop showing up each day."
                native:model="isActive"
            />
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
            :disabled="$saving || $savedId !== null"
            @tap="save"
        >
            {{ $submitLabel }}
        </button>
    </column>
</scroll-view>
