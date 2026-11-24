<native:top-bar title="New list" back />

@include('native.list-form', [
    'formRef' => 'create-list',
    'submitLabel' => 'Create list',
    'typeOptions' => $this->typeOptions,
    'ownerOptions' => $this->ownerOptions,
])
