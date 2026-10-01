@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($routine = $this->routine)

<native:top-bar :title="$routine ? 'Edit '.$routine->name : 'Edit routine'" back />

@if ($routine)
    @include('native.routine-form', [
        'formRef' => 'edit-routine',
        'submitLabel' => 'Update routine',
        'timeOfDayOptions' => $this->timeOfDayOptions,
        'frequencyOptions' => $this->frequencyOptions,
        'weekdayRows' => $this->weekdayRows,
        'frequency' => $this->frequency,
        'canDelete' => true,
        'queuedChange' => $this->queuedChange,
        'deleting' => $this->deleting,
    ])
@else
    <column ref="edit-routine-missing" fill center class="bg-theme-background ios:bg-theme-grouped-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This routine could not be found.
        </text>
    </column>
@endif
