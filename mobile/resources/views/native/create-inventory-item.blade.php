<native:top-bar title="Add something" back />

@include('native.inventory-item-form', [
    'formRef' => 'create-item',
    'submitLabel' => $this->selectedParent ? 'Save to '.$this->selectedParent['name'] : 'Save',
    'typeChoices' => $this->typeChoices,
    'selectedType' => $this->type,
    'selectedParent' => $this->selectedParent,
    'browsedParent' => $this->browsedParent,
    'parentPickerRows' => $this->parentPickerRows,
    'parentTrail' => $this->parentTrail,
])
