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

            @include('native.option-list', [
                'ref' => $formRef.'-time-of-day',
                'label' => 'Time of day',
                'options' => $timeOfDayOptions,
                'selected' => $timeOfDayIndex,
                'action' => 'chooseTimeOfDay',
            ])

            @include('native.option-list', [
                'ref' => $formRef.'-frequency',
                'label' => 'Repeats',
                'options' => $frequencyOptions,
                'selected' => $frequencyIndex,
                'action' => 'chooseFrequency',
            ])

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

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Starts on</text>
                <pressable
                    ref="{{ $formRef }}-starts-on"
                    :a11y-label="'Starts on '.$startsOnLabel"
                    a11y-hint="Choose when this routine starts"
                    class="min-h-12 w-full justify-center rounded-xl border border-theme-outline bg-theme-surface px-4 py-2"
                    @tap="openStartsOnPicker"
                >
                    <row class="w-full items-start gap-3">
                        <icon :ios="Ios::Calendar" :android="Android::CalendarMonth" :size="20" class="text-theme-primary" />
                        <column class="flex-1 items-start gap-1">
                            <text font="medium" class="text-base text-theme-on-surface">{{ $startsOnLabel }}</text>
                            <x-ui.badge size="sm">Change</x-ui.badge>
                        </column>
                    </row>
                </pressable>
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

<native:bottom-sheet ref="{{ $formRef }}-starts-on-picker" :visible="$showStartsOnPicker" @dismiss="closeStartsOnPicker" detents="fit">
    @if ($showStartsOnPicker)
        <column class="w-full bg-theme-background px-4 py-4">
            <date-picker
                ref="{{ $formRef }}-starts-on-calendar"
                label="Starts on"
                picker-style="inline"
                :value="$startsOn"
                @change="chooseStartsOn"
            />
        </column>
    @endif
</native:bottom-sheet>
