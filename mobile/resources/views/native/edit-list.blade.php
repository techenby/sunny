@php($checklist = $this->checklist)

<native:top-bar :title="$checklist ? 'Edit '.$checklist['name'] : 'Edit list'" back />

@if ($checklist)
    @include('native.list-form', [
        'formRef' => 'edit-list',
        'submitLabel' => 'Update list',
        'typeOptions' => $this->typeOptions,
    ])
@else
    <column ref="edit-list-missing" fill center class="bg-theme-background ios:bg-theme-grouped-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This list could not be found.
        </text>
    </column>
@endif
