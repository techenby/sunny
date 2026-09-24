<?php

use App\NativeComponents\EditRecipe;
use App\NativeComponents\Recipes;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('opens the edit screen from the recipe', function () {
    Native::visit('/recipes/1')
        ->tap('edit-recipe')
        ->assertNavigatedTo('/recipes/1/edit')
        ->follow()
        ->assertScreen(EditRecipe::class);
});

it('titles the screen with the recipe being edited', function () {
    Native::visit('/recipes/1/edit')
        ->assertNavTitle('Edit Buttermilk Pancakes');
});

it('prefills the form from the recipe', function () {
    Native::visit('/recipes/1/edit')
        ->assertSet('name', 'Buttermilk Pancakes')
        ->assertSet('source', 'https://www.allrecipes.com/recipe/buttermilk-pancakes/')
        ->assertSet('servings', '4')
        ->assertSet('prepTime', '10 minutes')
        ->assertSet('cookTime', '15 minutes')
        ->assertSet('totalTime', '25 minutes')
        ->assertSet('description', 'Fluffy weekend pancakes that come together in one bowl.')
        ->assertSet('notes', 'Let the batter rest for five minutes for taller pancakes.')
        ->assertSet('nutrition', '')
        ->assertSet('tags', ['breakfast', 'weekend']);
});

it('turns the stored html back into one line per item', function () {
    Native::visit('/recipes/1/edit')
        ->assertSet('ingredients', "2 cups flour\n2 cups buttermilk\n2 eggs\n3 tbsp melted butter\n2 tbsp sugar\n2 tsp baking powder")
        ->assertSet('instructions', "Whisk the dry ingredients together in a large bowl.\nStir in the buttermilk, eggs, and melted butter until just combined.\nCook ¼ cup of batter at a time on a hot griddle until bubbles form, then flip.");
});

it('round-trips the ingredients back to the stored shape when nothing is edited', function () {
    $harness = Native::visit('/recipes/1/edit');

    expect($harness->get('ingredientsHtml'))->toBe(Recipes::find(1)['ingredients']);
});

it('reads paragraph-style ingredients too', function () {
    Native::visit('/recipes/5/edit')
        ->assertSet('ingredients', "1 cup rolled oats\n1 cup milk\n½ cup Greek yogurt\n1 tbsp chia seeds")
        ->assertSet('instructions', 'Stir everything together in a jar, cover, and refrigerate overnight.');
});

it('keeps tags outside the chip vocabulary so editing cannot drop them', function () {
    $harness = Native::visit('/recipes/3/edit');

    expect($harness->get('tags'))->toBe(['dinner', 'family favorite'])
        ->and($harness->get('tagOptions'))->toContain('dinner', 'family favorite')
        ->and($harness->get('tagOptions'))->toHaveCount(count(EditRecipe::TAG_OPTIONS) + 1);

    $harness->assertElement('chip', fn (array $node): bool => ($node['props']['label'] ?? null) === 'family favorite'
        && ($node['props']['value'] ?? null) === true);
});

it('edits a field and keeps the rest intact', function () {
    Native::visit('/recipes/1/edit')
        ->input('recipe-name', 'Weekend Pancakes')
        ->input('recipe-servings', '6')
        ->assertSet('name', 'Weekend Pancakes')
        ->assertSet('servings', '6')
        ->assertSet('cookTime', '15 minutes');
});

it('toggles a tag off without touching the others', function () {
    Native::visit('/recipes/1/edit')
        ->toggle('recipe-tag-breakfast', false)
        ->assertSet('tags', ['weekend']);
});

it('refuses to update a recipe without a name', function () {
    Native::visit('/recipes/1/edit')
        ->input('recipe-name', '   ')
        ->tap('edit-recipe-submit')
        ->assertNoNavigation()
        ->assertSee('Give the recipe a name.');
});

it('keeps unsaved recipe edits on the form until uploads are implemented', function () {
    Native::visit('/recipes/1/edit')
        ->assertSee('Update recipe')
        ->tap('edit-recipe-submit')
        ->assertNoNavigation()
        ->assertSet('error', 'Saving changes is not available yet. Please edit this on the Sunny website.');
});

it('explains when the recipe does not exist', function () {
    Native::visit('/recipes/999/edit')
        ->assertNavTitle('Edit recipe')
        ->assertSee('This recipe could not be found.')
        ->assertMissingElement('outlined_text_input');
});

it('offers no edit action on a recipe that does not exist', function () {
    Native::visit('/recipes/999')
        ->assertMissingElement('top_bar_action');
});
