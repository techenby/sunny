<?php

use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('can view edit page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Oden']);

    $this->actingAs($user)
        ->get(route('recipes.edit', ['recipe' => $recipe]))
        ->assertOk()
        ->assertSee('Oden');
});

test('can edit a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Old Name', 'tags' => ['Dinner']]);

    Livewire::actingAs($user)
        ->test('pages::recipes.edit', ['recipe' => $recipe])
        ->assertSet('form.name', 'Old Name')
        ->assertSet('form.tags', ['Dinner'])
        ->set('form.name', 'New Name')
        ->set('form.tags', ['Dinner', 'Slow Cooker'])
        ->set('form.description', 'A delicious recipe')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->fresh())
        ->name->toBe('New Name')
        ->tags->toBe(['Dinner', 'Slow Cooker'])
        ->description->toBe('A delicious recipe');
});

test('can upload a photo to a recipe', function () {
    Storage::fake();

    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);

    Livewire::actingAs($user)
        ->test('pages::recipes.edit', ['recipe' => $recipe])
        ->set('form.photo', UploadedFile::fake()->image('photo.png'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->fresh())
        ->photo_path->toBe("teams/{$user->current_team_id}/recipes/chocolate-cake.png");

    Storage::assertExists("teams/{$user->current_team_id}/recipes/chocolate-cake.png");
});

test('can replace a photo on a recipe', function () {
    Storage::fake();

    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Chocolate Cake',
        'photo_path' => "teams/{$user->current_team_id}/recipes/chocolate-cake.png",
    ]);

    Storage::put($recipe->photo_path, 'old photo contents');

    Livewire::actingAs($user)
        ->test('pages::recipes.edit', ['recipe' => $recipe])
        ->assertSee('chocolate-cake.png')
        ->set('form.photo', UploadedFile::fake()->image('new-photo.jpg'))
        ->assertSee('new-photo.jpg')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->fresh())
        ->photo_path->toBe("teams/{$user->current_team_id}/recipes/chocolate-cake.jpg");

    Storage::assertMissing("teams/{$user->current_team_id}/recipes/chocolate-cake.png");
    Storage::assertExists("teams/{$user->current_team_id}/recipes/chocolate-cake.jpg");
});

test('can remove an existing photo', function () {
    Storage::fake();

    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Chocolate Cake',
        'photo_path' => "teams/{$user->current_team_id}/recipes/chocolate-cake.png",
    ]);

    Storage::put($recipe->photo_path, 'old photo contents');

    Livewire::actingAs($user)
        ->test('pages::recipes.edit', ['recipe' => $recipe])
        ->assertSee('chocolate-cake.png')
        ->call('removePhoto')
        ->assertDontSee('chocolate-cake.png')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->fresh())
        ->photo_path->toBeNull();

    Storage::assertMissing("teams/{$user->current_team_id}/recipes/chocolate-cake.png");
});

test('can remove a temporary uploaded photo', function () {
    Storage::fake();

    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Chocolate Cake',
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.edit', ['recipe' => $recipe])
        ->set('form.photo', UploadedFile::fake()->image('photo.png'))
        ->assertSee('photo.png')
        ->call('removePhoto')
        ->assertDontSee('photo.png');
});

test('can remove existing photo then upload a new one', function () {
    Storage::fake();

    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Chocolate Cake',
        'photo_path' => "teams/{$user->current_team_id}/recipes/chocolate-cake.png",
    ]);

    Storage::put($recipe->photo_path, 'old photo contents');

    Livewire::actingAs($user)
        ->test('pages::recipes.edit', ['recipe' => $recipe])
        ->assertSee('chocolate-cake.png')
        ->call('removePhoto')
        ->assertDontSee('chocolate-cake.png')
        ->set('form.photo', UploadedFile::fake()->image('new-photo.jpg'))
        ->assertSee('new-photo.jpg')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->fresh())
        ->photo_path->toBe("teams/{$user->current_team_id}/recipes/chocolate-cake.jpg");

    Storage::assertMissing("teams/{$user->current_team_id}/recipes/chocolate-cake.png");
    Storage::assertExists("teams/{$user->current_team_id}/recipes/chocolate-cake.jpg");
});

test('cannot edit a recipe from another team', function () {
    $user = User::factory()->create();
    $otherRecipe = Recipe::factory()->for(Team::factory())->create();

    $this->actingAs($user)
        ->get(route('recipes.edit', $otherRecipe))
        ->assertForbidden();
});
