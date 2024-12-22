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
        ->assertOk()
        ->set('form.name', 'Oden')
        ->set('form.image', $image)
        ->call('save');

    $recipe = Recipe::firstWhere('name', 'Oden');

    expect($recipe->slug)->toBe('oden');
    expect($recipe->media)->not->toBeEmpty();
});
