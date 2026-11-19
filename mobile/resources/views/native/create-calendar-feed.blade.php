<native:top-bar title="Add calendar" back />

@include('native.calendar-feed-form', ['formRef' => 'create-calendar', 'colorOptions' => $this->colorOptions])
