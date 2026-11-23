@use('App\Enums\RoutineFrequency')
@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($frequency = RoutineFrequency::cases()[$frequencyIndex] ?? RoutineFrequency::Daily)

<x-ui.submit-bar
    ref="{{ $formRef }}-submit"
    :disabled="$saving || $savedId !== null"
>
    {{ $submitLabel }}
</x-ui.submit-bar>

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-4 py-6">
        <column class="w-full gap-4">
            <outlined-text-input
                ref="{{ $formRef }}-name"
                label="Name"
                placeholder="Morning routine"
                autocapitalize="sentences"
                native:model.blur="name"
            />

            <select
                ref="{{ $formRef }}-time-of-day"
                label="Time of day"
                :options="$timeOfDayOptions"
                :value="$timeOfDayOptions[$timeOfDayIndex] ?? ''"
                @change="pickTimeOfDay"
            />

            <select
                ref="{{ $formRef }}-frequency"
                label="Repeats"
                :options="$frequencyOptions"
                :value="$frequencyOptions[$frequencyIndex] ?? ''"
                @change="pickFrequency"
            />

            @if ($frequency === RoutineFrequency::Weekly)
                <column class="w-full gap-1">
                    <x-ui.field-label>On these days</x-ui.field-label>
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
                    native:model.blur="dayOfMonth"
                />
            @endif

            <column class="w-full gap-1">
                <x-ui.picker-field
                    ref="{{ $formRef }}-starts-on"
                    label="Starts on"
                    :value="$startsOnLabel"
                    opens="date"
                    :a11y-label="'Starts on '.$startsOnLabel"
                    a11y-hint="Choose when this routine starts"
                    tap="openStartsOnPicker"
                />
                @if ($startsOnHint !== '')
                    <row ref="{{ $formRef }}-starts-on-hint" class="w-full items-start gap-1.5">
                        <icon :ios="Ios::InfoCircle" :android="Android::Info" :size="16" class="text-theme-on-surface-variant" />
                        <text class="flex-1 text-sm text-theme-on-surface-variant">{{ $startsOnHint }}</text>
                    </row>
                @endif
            </column>

            @if ($ownerOptions)
                @include('native.owner-picker', [
                    'ref' => $formRef.'-owner',
                    'label' => 'Assigned to',
                    'members' => $ownerOptions,
                    'selected' => $userId,
                ])
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
    </column>
</scroll-view>

<native:bottom-sheet ref="{{ $formRef }}-starts-on-picker" :visible="$showStartsOnPicker" @dismiss="closeStartsOnPicker" detents="fit">
    @if ($showStartsOnPicker)
        <column class="w-full bg-theme-background">
            <x-ui.sheet-header ref="{{ $formRef }}-starts-on-close" title="Starts on" close="closeStartsOnPicker" />
            <column class="w-full px-4 pb-4">
                <date-picker
                    ref="{{ $formRef }}-starts-on-calendar"
                    label="Starts on"
                    picker-style="inline"
                    :value="$startsOn"
                    @change="chooseStartsOn"
                />
            </column>
        </column>
    @endif
</native:bottom-sheet>
