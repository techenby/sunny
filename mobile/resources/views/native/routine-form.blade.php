@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- Shared by the create and edit screens. Expects $formRef, $submitLabel,
     $timeOfDayOptions, $frequencyOptions, $frequency, $assigneeOptions,
     $weekdayOptions, and optionally $queuedChange; the remaining state comes
     from the including component's ManagesRoutineForm properties. --}}

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        @isset($queuedChange)
            @include('native.queued-change', ['refPrefix' => $formRef, 'queuedChange' => $queuedChange])
        @endisset

        <column class="w-full gap-4">
            <outlined-text-input
                ref="{{ $formRef }}-name"
                label="Name"
                placeholder="Bedtime"
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
                <text class="text-sm font-semibold text-theme-on-surface-variant">Assigned to</text>
                <button-group
                    ref="{{ $formRef }}-assignee"
                    :options="$assigneeOptions"
                    a11y-label="Assigned to"
                    native:model="assigneeIndex"
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

            @if ($frequency->usesWeekdays())
                <row class="w-full items-center justify-start gap-2">
                    @foreach ($weekdayOptions as $day => $label)
                        <chip
                            ref="{{ $formRef }}-weekday-{{ $day }}"
                            :label="$label"
                            :selected="in_array($day, $weekdays, true)"
                            @change="toggleWeekday({{ $day }})"
                        />
                    @endforeach
                </row>
            @endif

            @if ($frequency->usesDayOfMonth())
                <outlined-text-input
                    ref="{{ $formRef }}-day-of-month"
                    label="Day of the month"
                    placeholder="1"
                    keyboard="number"
                    :ios-leading-icon="Ios::Calendar"
                    :android-leading-icon="Android::CalendarMonth"
                    native:model.blur="dayOfMonth"
                />
            @endif

            <row class="w-full items-center gap-3">
                <checkbox
                    ref="{{ $formRef }}-active"
                    :value="$isActive"
                    a11y-label="Active"
                    @change="toggleActive"
                />
                <column class="flex-1 gap-0.5">
                    <text class="text-base text-theme-on-surface">Active</text>
                    <text class="text-sm text-theme-on-surface-variant">Paused routines stay off the board.</text>
                </column>
            </row>
        </column>

        <column class="w-full gap-2">
            <text class="text-sm font-semibold text-theme-on-surface-variant">Steps</text>

            @foreach ($steps as $index => $step)
                <row class="w-full items-center gap-2">
                    <outlined-text-input
                        ref="{{ $formRef }}-step-{{ $index }}"
                        class="flex-1"
                        a11y-label="Step {{ $index + 1 }}"
                        autocapitalize="sentences"
                        :value="$step['name']"
                        sync-mode="blur"
                        @change="renameStep({{ $index }})"
                    />
                    <pressable
                        ref="{{ $formRef }}-step-{{ $index }}-remove"
                        class="h-12 w-12 items-center justify-center"
                        a11y-label="Remove step {{ $index + 1 }}"
                        @tap="removeStep({{ $index }})"
                    >
                        <native:icon :ios="Ios::Xmark" :android="Android::Close" :size="20" class="text-theme-on-surface-variant" />
                    </pressable>
                </row>
            @endforeach

            <row class="w-full items-center gap-2">
                <outlined-text-input
                    ref="{{ $formRef }}-new-step"
                    class="flex-1"
                    placeholder="Add a step"
                    a11y-label="Add a step"
                    autocapitalize="sentences"
                    keep-focus-on-submit
                    native:model="newStep"
                    @submit="addStep"
                />
                <pressable
                    ref="{{ $formRef }}-add-step"
                    class="h-12 w-12 items-center justify-center"
                    a11y-label="Add step"
                    @tap="addStep"
                >
                    <native:icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-primary" />
                </pressable>
            </row>
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
