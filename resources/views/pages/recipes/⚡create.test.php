<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        ->set('form.tags', ['Dessert'])
        ->set('form.servings', '8 people')
        ->set('form.prep_time', '20m')
        ->set('form.cook_time', '40m')
        ->set('form.total_time', '1h')
        ->set('form.ingredients', "Flour\nSugar\nCocoa\nEggs")
        ->set('form.instructions', "Mix ingredients\nBake at 350F")
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.index'));

    expect(Recipe::firstWhere('name', 'Chocolate Cake'))->not->toBeNull()
        ->team_id->toBe($user->current_team_id)
        ->slug->toBe('chocolate-cake')
        ->tags->toBe(['Dessert'])
        ->servings->toBe('8 people')
        ->prep_time->toBe('20m')
        ->cook_time->toBe('40m')
        ->total_time->toBe('1h');
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
                'recipeCategory' => 'Dinner',
                'keywords' => 'slow cooker, vegetarian',
            ]) . '</script></head><body></body></html>'
        ),
    ]);

    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.source', 'https://example.com/recipe')
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('form.name', 'Imported Recipe')
        ->assertSet('form.description', 'A great recipe')
        ->assertSet('form.servings', '4 servings')
        ->assertSet('form.prep_time', '20m')
        ->assertSet('form.cook_time', '40m')
        ->assertSet('form.total_time', '1h')
        ->assertSet('form.tags', ['Dinner', 'Slow Cooker', 'Vegetarian']);
});

test('import does not set tags when none match', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><head><script type="application/ld+json">' . json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Recipe',
                'name' => 'Imported Recipe',
                'keywords' => 'some random tag, another tag',
            ]) . '</script></head><body></body></html>'
        ),
    ]);

    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.source', 'https://example.com/recipe')
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('form.tags', []);
});

test('import validates url is required', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.source', 'not-a-url')
        ->call('import')
        ->assertHasErrors(['form.source']);
});

test('can upload a photo to a recipe', function () {
    Storage::fake();

    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.name', 'Chocolate Cake')
        ->set('form.photo', UploadedFile::fake()->image('download.png'))
        ->assertSee('download.png')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.index'));

    expect(Recipe::firstWhere('name', 'Chocolate Cake'))->not->toBeNull()
        ->team_id->toBe($user->current_team_id)
        ->photo_path->toBe("teams/{$user->current_team_id}/recipes/chocolate-cake.png");

    Storage::assertExists("teams/{$user->current_team_id}/recipes/chocolate-cake.png");
});

test('rejects non-image file uploads', function () {
    Storage::fake();

    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.name', 'Chocolate Cake')
        ->set('form.photo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['form.photo']);
});

test('rejects photo exceeding 5MB', function () {
    Storage::fake();

    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.name', 'Chocolate Cake')
        ->set('form.photo', UploadedFile::fake()->image('large.png')->size(6000))
        ->call('save')
        ->assertHasErrors(['form.photo']);
});

test('can remove a temporary uploaded photo', function () {
    Storage::fake();

    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.name', 'Chocolate Cake')
        ->set('form.photo', UploadedFile::fake()->image('download.png'))
        ->assertSee('download.png')
        ->call('removePhoto')
        ->assertDontSee('download.png')
        ->assertSet('form.photo', null);
});
