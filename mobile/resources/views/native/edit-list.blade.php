@php($checklist = $this->checklist)

<native:top-bar title="Edit list" back />

@if ($checklist)
    @include('native.list-form', [
        'formRef' => 'edit-list',
        'submitLabel' => 'Save',
        'typeOptions' => $this->typeOptions,
        'ownerOptions' => $this->ownerOptions,
    ])
@else
    <x-ui.not-found ref="edit-list-missing" thing="list" class="ios:bg-theme-grouped-background" />
@endif
