@php($recipe = $this->recipe)

<native:top-bar title="Edit recipe" back />

@if ($recipe)
    @include('native.recipe-form', [
        'formRef' => 'edit-recipe',
        'submitLabel' => 'Save',
        'tagRows' => $this->tagRows,
        'importCard' => $this->importCard,
        'remixOf' => $this->remixOf,
    ])
@else
    <x-ui.not-found ref="edit-recipe-missing" thing="recipe" class="ios:bg-theme-grouped-background" />
@endif
