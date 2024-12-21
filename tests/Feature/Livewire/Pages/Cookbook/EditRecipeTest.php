<?php

use App\Livewire\Pages\Cookbook\EditRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    $this->actingAs($user)
        ->get('/cookbook/recipes/' . $recipe->id)
        ->assertOk()
        ->assertSee('Oden');
});

test('can view component', function () {
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertOk()
        ->assertSee('Oden');
});

test('can attach image', function () {
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    $image = UploadedFile::fake()->image('image.jpg');

    Livewire::test(EditRecipe::class, ['recipe' => $recipe])
        ->assertOk()
        ->assertSet('form.name', 'Oden')
        ->set('form.image', $image)
        ->call('save');

    $this->assertNotEmpty($recipe->media);
});
