<native:top-bar :title="$this->title" back />

@include('native.inventory-item-form', [
    'formRef' => 'create-item',
    'submitLabel' => $this->selectedParent ? 'Add to '.$this->selectedParent['name'] : 'Add to inventory',
    'typeChoices' => $this->typeChoices,
    'selectedType' => $this->type,
    'selectedParent' => $this->selectedParent,
    'browsedParent' => $this->browsedParent,
    'parentPickerRows' => $this->parentPickerRows,
    'parentTrail' => $this->parentTrail,
])
