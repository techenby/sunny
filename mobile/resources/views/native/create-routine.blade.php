<native:top-bar title="New routine" back />

@include('native.routine-form', [
    'formRef' => 'create-routine',
    'submitLabel' => 'Save routine',
    'timeOfDayOptions' => $this->timeOfDayOptions,
    'frequencyOptions' => $this->frequencyOptions,
    'weekdayRows' => $this->weekdayRows,
])
