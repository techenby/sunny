<native:top-bar title="New calendar" back />

@include('native.calendar-feed-form', ['formRef' => 'create-calendar', 'colorOptions' => $this->colorOptions, 'submitLabel' => 'Create calendar'])
