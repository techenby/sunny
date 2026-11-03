@php($routine = $this->routine)

<native:top-bar title="Edit routine" back />

@if ($routine)
    @include('native.routine-form', [
        'formRef' => 'edit-routine',
        'submitLabel' => 'Update routine',
        'timeOfDayOptions' => $this->timeOfDayOptions,
        'frequencyOptions' => $this->frequencyOptions,
        'weekdayRows' => $this->weekdayRows,
    ])
@else
    <x-ui.not-found ref="edit-routine-missing" thing="routine" class="ios:bg-theme-grouped-background" />
@endif
