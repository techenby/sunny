<?php

use App\Livewire\Pages\Cookbook\CreateRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/cookbook/recipes/create')
        ->assertOk()
        ->assertSee('Create New Recipe');
});

test('can view component', function () {
    Livewire::test(CreateRecipe::class)
        ->assertOk()
        ->assertSee('Create New Recipe');
});

test('can create recipe', function () {
    Storage::fake();

    $image = UploadedFile::fake()->image('image.jpg');

    Livewire::test(CreateRecipe::class)
        ->set('form.name', 'Oden')
        ->set('form.image', $image)
        ->call('save')
        ->assertOk();

    $recipe = Recipe::firstWhere('name', 'Oden');

    expect($recipe->slug)->toBe('oden');
    expect($recipe->media)->not->toBeEmpty();
});

test('can clear image', function () {
    Storage::fake();

    $image = UploadedFile::fake()->image('image.jpg');

    Livewire::test(CreateRecipe::class)
        ->set('form.name', 'Oden')
        ->set('form.image', $image)
        ->call('clear')
        ->assertSet('form.image', null)
        ->assertOk();
});

test('preview is visable with image', function () {
    Storage::fake();

    $image = UploadedFile::fake()->image('image.jpg');

    Livewire::test(CreateRecipe::class)
        ->set('form.image', $image)
        ->assertSeeHtml('id="preview-image"')
        ->assertOk();
});

test('preview is hidden without image', function () {
    Livewire::test(CreateRecipe::class)
        ->assertDontSeeHtml('id="preview-image"')
        ->assertOk();
});
