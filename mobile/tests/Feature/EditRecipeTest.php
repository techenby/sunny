<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Models\PendingWrite;
use App\Models\Recipe;
use App\NativeComponents\EditRecipe;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;

beforeEach(fn () => seedSunnyData());

it('opens the edit screen from the recipe', function () {
    Native::visit('/recipes/1')
        ->tap('recipe-edit')
        ->assertNavigatedTo('/recipes/1/edit')
        ->follow()
        ->assertScreen(EditRecipe::class);
});

it('titles the edit screen without repeating the recipe name', function () {
    Native::visit('/recipes/1/edit')
        ->assertNavTitle('Edit recipe');
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

it('turns the stored rows back into one line per item', function () {
    Native::visit('/recipes/1/edit')
        ->assertSet('ingredients', "2 cups flour\n2 cups buttermilk\n2 eggs\n3 tbsp melted butter\n2 tbsp sugar\n2 tsp baking powder")
        ->assertSet('instructions', "Whisk the dry ingredients together in a large bowl.\nStir in the buttermilk, eggs, and melted butter until just combined.\nCook ¼ cup of batter at a time on a hot griddle until bubbles form, then flip.");
});

it('shows headings as # lines', function () {
    Recipe::find(1)->update(['ingredients' => [['type' => 'heading', 'text' => 'Dry'], ['type' => 'item', 'text' => '2 cups flour']]]);

    Native::visit('/recipes/1/edit')->assertSet('ingredients', "# Dry\n2 cups flour");
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

it('keeps the recipe edit queued on the phone when Sunny rejects the session', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 401)]);

    Native::visit('/recipes/1/edit')
        ->assertSee('Save')
        ->tap('edit-recipe-submit')
        ->assertSet('error', '')
        ->assertWentBack();

    expect(PendingWrite::sole()->error)->toBeNull();
});

it('explains when the recipe does not exist', function () {
    Native::visit('/recipes/999/edit')
        ->assertNavTitle('Edit recipe')
        ->assertSee('Recipe not found')
        ->assertMissingElement('outlined_text_input');
});

it('offers no edit action on a recipe that does not exist', function () {
    Native::visit('/recipes/999')
        ->assertMissingElement('top_bar_action');
});
