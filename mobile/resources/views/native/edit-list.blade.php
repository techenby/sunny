@php($checklist = $this->checklist)

<native:top-bar title="Edit list" back />

@if ($checklist)
    @include('native.list-form', [
        'formRef' => 'edit-list',
        'submitLabel' => 'Update list',
        'typeOptions' => $this->typeOptions,
    ])
@else
    <x-ui.not-found ref="edit-list-missing" thing="list" class="ios:bg-theme-grouped-background" />
@endif
