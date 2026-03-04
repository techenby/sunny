<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('can view page', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('recipes.create'))
        ->assertOk();
});

test('can create a recipe', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.name', 'Chocolate Cake')
        ->set('form.servings', '8 people')
        ->set('form.prep_time', '20 min')
        ->set('form.cook_time', '40 min')
        ->set('form.total_time', '1 hour')
        ->set('form.ingredients', "Flour\nSugar\nCocoa\nEggs")
        ->set('form.instructions', "Mix ingredients\nBake at 350F")
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.index'));

    expect(Recipe::firstWhere('name', 'Chocolate Cake'))->not->toBeNull()
        ->team_id->toBe($user->current_team_id)
        ->slug->toBe('chocolate-cake')
        ->servings->toBe('8 people')
        ->prep_time->toBe('20 min')
        ->cook_time->toBe('40 min')
        ->total_time->toBe('1 hour');
});

test('can import recipe from url', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><head><script type="application/ld+json">' . json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Recipe',
                'name' => 'Imported Recipe',
                'description' => 'A great recipe',
                'recipeYield' => '4 servings',
                'prepTime' => 'PT20M',
                'cookTime' => 'PT40M',
                'totalTime' => 'PT1H',
                'recipeIngredient' => ['Flour', 'Sugar'],
                'recipeInstructions' => [
                    ['@type' => 'HowToStep', 'text' => 'Mix.'],
                ],
            ]) . '</script></head><body></body></html>'
        ),
    ]);

    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('importUrl', 'https://example.com/recipe')
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('form.name', 'Imported Recipe')
        ->assertSet('form.description', 'A great recipe')
        ->assertSet('form.servings', '4 servings')
        ->assertSet('form.prep_time', '20 min')
        ->assertSet('form.cook_time', '40 min')
        ->assertSet('form.total_time', '1 hr');
});

test('import validates url is required', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('importUrl', 'not-a-url')
        ->call('import')
        ->assertHasErrors(['importUrl']);
});
