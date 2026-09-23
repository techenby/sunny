@php($recipe = $this->recipe)

<native:top-bar :title="$recipe ? 'Edit '.$recipe['name'] : 'Edit recipe'" back />

@if ($recipe)
    @include('native.recipe-form', [
        'formRef' => 'edit-recipe',
        'submitLabel' => 'Update recipe',
    'tagRows' => $this->tagRows,
    ])
@else
    <column ref="edit-recipe-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This recipe could not be found.
        </text>
    </column>
@endif
