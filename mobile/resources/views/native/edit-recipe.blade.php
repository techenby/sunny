@php($recipe = $this->recipe)

<native:top-bar title="Edit recipe" back />

@if ($recipe)
    @include('native.recipe-form', [
        'formRef' => 'edit-recipe',
        'submitLabel' => 'Update recipe',
    'tagRows' => $this->tagRows,
    ])
@else
    <x-ui.not-found ref="edit-recipe-missing" thing="recipe" class="ios:bg-theme-grouped-background" />
@endif
