<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

$cookableRecipe = fn (User $user, array $attributes = []): Recipe => Recipe::factory()->for($user->currentTeam)->create([
    'name' => 'Apple Cider Donuts',
    'servings' => '12 donuts',
    'ingredients' => "# Wet\n1/4 cup granulated sugar\n2 Tbsp brown sugar\n# Dry\n1 1/2 cups flour\nSalt, to taste",
    'instructions' => "Preheat oven to 375°F.\n# Bake\nBake for 8–9 minutes. Cool in the pan for 3–5 minutes.\nServe.",
    ...$attributes,
]);

test('can cook a recipe', function () use ($cookableRecipe) {
    $user = User::factory()->create();
    $recipe = $cookableRecipe($user);

    $this->actingAs($user)
        ->get(route('recipes.cook', $recipe))
        ->assertOk()
        ->assertSeeInOrder(['Apple Cider Donuts', 'Servings', '12', 'Exit cook mode'])
        ->assertSeeInOrder(['Ingredients', '0', 'of 4 ready', 'Wet', '1/4 cup', 'granulated sugar', 'Dry', '1 1/2 cups', 'flour', 'Salt, to taste'])
        ->assertSeeInOrder(['Instructions', 'Step', '1', 'of 3', 'Current step', 'Preheat oven', 'Bake', 'Bake for 8–9 minutes', 'Serve.']);
});

test('cannot cook a recipe from another team', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('recipes.cook', Recipe::factory()->create()))
        ->assertForbidden();
});

test('the recipe page links to cook mode only when there is something to cook', function () use ($cookableRecipe) {
    $user = User::factory()->create();
    $recipe = $cookableRecipe($user);
    $empty = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)->get(route('recipes.show', $recipe))->assertSee(route('recipes.cook', $recipe));
    $this->actingAs($user)->get(route('recipes.show', $empty))->assertDontSee(route('recipes.cook', $empty));
});

test('changing the servings scales the ingredient amounts', function () use ($cookableRecipe) {
    $user = User::factory()->create();

    $page = Livewire::actingAs($user)
        ->test('pages::recipes.cook', ['recipe' => $cookableRecipe($user, ['servings' => '2'])])
        ->assertSet('servings', 2)
        ->call('changeServings', 1)
        ->call('changeServings', 1)
        ->assertSet('servings', 4);

    expect($page->html())->toContain('1/2 cup', '4 Tbsp', '3 cups', 'Salt, to taste');

    $page->call('changeServings', -10)->assertSet('servings', 1);

    expect($page->html())->toContain('1/8 cup', '1 Tbsp', '3/4 cup');
});

test('recipes without a serving count cannot be scaled', function () use ($cookableRecipe) {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.cook', ['recipe' => $cookableRecipe($user, ['servings' => null])])
        ->assertSet('servings', null)
        ->assertDontSee('Servings')
        ->call('changeServings', 1)
        ->assertSet('servings', null)
        ->assertSee('1/4 cup');
});

test('ingredients can be ticked off, but headings cannot', function () use ($cookableRecipe) {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.cook', ['recipe' => $cookableRecipe($user)])
        ->call('toggleIngredient', 0)
        ->assertSet('checked', [])
        ->call('toggleIngredient', 1)
        ->call('toggleIngredient', 4)
        ->assertSet('checked', [1 => '1/4 cup granulated sugar', 4 => '1 1/2 cups flour'])
        ->call('toggleIngredient', 1)
        ->assertSet('checked', [4 => '1 1/2 cups flour'])
        ->call('toggleIngredient', 99)
        ->assertSet('checked', [4 => '1 1/2 cups flour']);
});

test('the current step can be moved and stays within the recipe', function () use ($cookableRecipe) {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.cook', ['recipe' => $cookableRecipe($user)])
        ->assertSet('step', 1)
        ->call('goToStep', 2)
        ->assertSet('step', 2)
        ->assertSeeInOrder(['Step', '2', 'of 3'])
        ->call('goToStep', 9)
        ->assertSet('step', 3)
        ->call('goToStep', 0)
        ->assertSet('step', 1);
});

test('progress is remembered when the page is opened again', function () use ($cookableRecipe) {
    $user = User::factory()->create();
    $recipe = $cookableRecipe($user);

    Livewire::actingAs($user)
        ->test('pages::recipes.cook', ['recipe' => $recipe])
        ->call('changeServings', 6)
        ->call('toggleIngredient', 1)
        ->call('goToStep', 2);

    Livewire::actingAs($user)
        ->test('pages::recipes.cook', ['recipe' => $recipe])
        ->assertSet('servings', 18)
        ->assertSet('checked', [1 => '1/4 cup granulated sugar'])
        ->assertSet('step', 2);
});

test('steps offer timers for their durations, except very long ones', function () use ($cookableRecipe) {
    $user = User::factory()->create();
    $recipe = $cookableRecipe($user, ['instructions' => "Bake for 8–9 minutes. Cool for 3 minutes.\nBrine for 24 hours.\nServe."]);

    Livewire::actingAs($user)
        ->test('pages::recipes.cook', ['recipe' => $recipe])
        ->assertSeeHtml("cookTimer('cook-timer-{$recipe->id}-1', JSON.parse('[480,180]')")
        ->assertDontSeeHtml("cook-timer-{$recipe->id}-2")
        ->assertDontSeeHtml("cook-timer-{$recipe->id}-3");
});
