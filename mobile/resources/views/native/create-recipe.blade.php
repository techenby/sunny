<native:top-bar title="New recipe" back />

@include('native.recipe-form', [
    'formRef' => 'create-recipe',
    'submitLabel' => 'Save recipe',
    'tagRows' => $this->tagRows,
])
