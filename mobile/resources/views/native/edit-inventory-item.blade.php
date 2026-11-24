@php($item = $this->item)

<native:top-bar :title="$this->title" back />

@if ($item)
    @include('native.inventory-item-form', [
        'formRef' => 'edit-item',
        'submitLabel' => 'Save',
        'typeChoices' => $this->typeChoices,
        'selectedType' => $this->type,
        'selectedParent' => $this->selectedParent,
        'browsedParent' => $this->browsedParent,
        'parentPickerRows' => $this->parentPickerRows,
        'parentTrail' => $this->parentTrail,
        'photoMenus' => $this->photoMenus('edit-item'),
    ])
@else
    <x-ui.not-found ref="edit-item-missing" thing="thing" class="ios:bg-theme-grouped-background" />
@endif
