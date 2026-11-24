<native:top-bar title="New calendar" back />

@include('native.calendar-feed-form', ['formRef' => 'create-calendar', 'colorOptions' => $this->colorOptions, 'disconnected' => $this->disconnected, 'submitLabel' => 'Create calendar'])
