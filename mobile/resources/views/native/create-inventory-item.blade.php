<native:top-bar title="New item" back />

@include('native.inventory-item-form', [
    'teamOptions' => $this->teamOptions,
    'formRef' => 'create-item',
    'submitLabel' => 'Save item',
    'subtitle' => 'Name it, pick what kind of thing it is, and choose where it lives.',
    'typeOptions' => $this->typeOptions,
    'parentOptions' => $this->parentOptions,
])
