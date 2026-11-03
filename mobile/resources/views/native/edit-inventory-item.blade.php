@php($item = $this->item)

<native:top-bar title="Edit item" back />

@if ($item)
    @include('native.inventory-item-form', [
        'formRef' => 'edit-item',
        'submitLabel' => 'Save changes',
        'typeChoices' => $this->typeChoices,
        'selectedType' => $this->type,
        'selectedParent' => $this->selectedParent,
        'browsedParent' => $this->browsedParent,
        'parentPickerRows' => $this->parentPickerRows,
        'parentTrail' => $this->parentTrail,
    ])
@else
    <x-ui.not-found ref="edit-item-missing" thing="item" class="ios:bg-theme-grouped-background" />
@endif
