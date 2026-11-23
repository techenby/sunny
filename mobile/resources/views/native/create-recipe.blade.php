<native:top-bar title="New recipe" back />

@include('native.recipe-form', [
    'formRef' => 'create-recipe',
    'submitLabel' => 'Create recipe',
    'tagRows' => $this->tagRows,
    'importCard' => $this->importCard,
])
