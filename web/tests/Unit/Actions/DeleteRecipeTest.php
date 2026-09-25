<?php

use App\Actions\Recipes\DeleteRecipe;
use App\Models\Recipe;

test('it deletes the recipe', function () {
    $recipe = Recipe::factory()->create();

    (new DeleteRecipe)->handle($recipe);

    expect(Recipe::find($recipe->id))->toBeNull();
});
