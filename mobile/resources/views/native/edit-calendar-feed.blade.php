@php($feed = $this->feed)

<native:top-bar title="Edit calendar" back />

@if ($feed)
    @include('native.calendar-feed-form', ['formRef' => 'edit-calendar', 'colorOptions' => $this->colorOptions, 'disconnected' => $this->disconnected, 'submitLabel' => 'Save', 'failure' => $this->failure])
@else
    <x-ui.not-found ref="edit-calendar-missing" thing="calendar" class="ios:bg-theme-grouped-background" />
@endif
