<native:top-bar title="New list" back />

@include('native.list-form', [
    'formRef' => 'create-list',
    'submitLabel' => 'Save list',
    'typeOptions' => $this->typeOptions,
])
