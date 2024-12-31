<?php

use App\Livewire\Pages\Cookbook\EditRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    $this->actingAs($user)
        ->get('/cookbook/recipes/' . $recipe->id)
        ->assertSee('Oden')
        ->assertOk();
});

test('can view component', function () {
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertSee('Oden')
        ->assertOk();
});

test('can attach image', function () {
    Storage::fake();

    $recipe = Recipe::factory()->create(['name' => 'Oden']);
    $image = UploadedFile::fake()->image('image.jpg');

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertSet('form.name', 'Oden')
        ->set('form.image', $image)
        ->call('save')
        ->assertOk();

    expect($recipe->fresh()->media)->not->toBeEmpty();
});

test('can clear image', function () {
    Storage::fake();

    $recipe = Recipe::factory()->create(['name' => 'Oden']);
    $image = UploadedFile::fake()->image('image.jpg');

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->set('form.name', 'Oden')
        ->set('form.image', $image)
        ->call('clear')
        ->assertSet('form.image', null)
        ->assertOk();
});

test('can preview new image', function () {
    Storage::fake();

    $recipe = Recipe::factory()->create(['name' => 'Oden']);
    $image = UploadedFile::fake()->image('image.jpg');

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertSet('form.name', 'Oden')
        ->set('form.image', $image)
        ->assertSeeHtml('id="preview-image')
        ->assertOk();
});

test('can preview existing image', function () {
    Storage::fake();

    $recipe = Recipe::factory()->withImage()->create(['name' => 'Oden']);

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertSeeHtml('id="preview-image')
        ->assertOk();
});

test('preview is hidden without image', function () {
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertDontSeeHtml('id="preview-image"')
        ->assertOk();
});

test('can replace image', function () {
    Storage::fake();

    $recipe = Recipe::factory()->withImage()->create(['name' => 'Oden']);
    $image = UploadedFile::fake()->image('new-image.jpg');

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertOk()->assertSeeHtml('id="preview-image"')
        ->assertSet('form.name', 'Oden')
        ->call('clear')->assertOk()->assertDontSeeHtml('id="preview-image"')
        ->set('form.image', $image)
        ->assertOk()->assertSeeHtml('id="preview-image"')
        ->call('save')
        ->assertOk();

    expect($recipe->fresh()->media)->not->toBeEmpty();
    expect($recipe->fresh()->media)->toHaveCount(1);
});

test('can update servings', function () {
    $recipe = Recipe::factory()->create(['name' => 'Oden', 'servings' => 2]);

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertSet('form.name', 'Oden')
        ->assertSet('form.servings', '2')
        ->set('form.servings', 1)
        ->call('save')
        ->assertOk();

    expect($recipe->fresh()->servings)->toBe('1');
});

test('can edit categories', function () {
    $recipe = Recipe::factory()->create(['name' => 'Drunken Chicken – J Gumbo Inspired']);
    $recipe->attachTags(['Slow Cooker', 'Dinner']);

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertSet('form.name', 'Drunken Chicken – J Gumbo Inspired')
        ->assertSet('form.categories', ['Slow Cooker', 'Dinner'])
        ->set('form.categories', ['Slow Cooker', 'Supper'])
        ->call('save')
        ->assertOk();

    $recipe = Recipe::firstWhere('name', 'Drunken Chicken – J Gumbo Inspired');

    expect($recipe->tags)->not->toBeEmpty();
    expect($recipe->tags->pluck('name'))->toContain('Slow Cooker', 'Supper');
});
