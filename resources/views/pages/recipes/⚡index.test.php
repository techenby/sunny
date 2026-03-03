<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('recipes.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the recipes page', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('recipes.index'))
        ->assertOk();
});

test('renders recipes for the current team only', function () {
    $user = User::factory()->withTeam()->create();
    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Pasta Carbonara']);
    Recipe::factory()->create(['name' => 'Chicken Tikka']);

    Livewire::actingAs($user)
        ->test('pages::recipes.index')
        ->assertSee('Pasta Carbonara')
        ->assertDontSee('Chicken Tikka');
});

test('can search recipes by name', function () {
    $user = User::factory()->withTeam()->create();
    Recipe::factory()
        ->count(2)
        ->for($user->currentTeam)
        ->sequence(['name' => 'Pasta Carbonara'], ['name' => 'Chicken Tikka'])
        ->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.index')
        ->set('search', 'Pasta')
        ->assertSee('Pasta Carbonara')
        ->assertDontSee('Chicken Tikka');
});

test('can sort recipes', function () {
    $user = User::factory()->withTeam()->create();
    Recipe::factory()->count(2)->for($user->currentTeam)->sequence(['name' => 'Zucchini Bread'], ['name' => 'Apple Pie'])->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.index')
        ->assertSeeInOrder(['Apple Pie', 'Zucchini Bread'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Zucchini Bread', 'Apple Pie']);
});

test('can delete a recipe', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.index')
        ->call('delete', $recipe->id);

    expect($recipe->fresh())->toBeNull();
});

test('cannot delete a recipe from another team', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.index')
        ->call('delete', $recipe->id);
})->throws(ModelNotFoundException::class);

test('shows source url as shortened link', function () {
    $user = User::factory()->withTeam()->create();
    Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Test Recipe',
        'source' => 'https://www.example.com/recipes/chocolate-cake',
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.index')
        ->assertSee('example.com');
});

test('can create a remix of a recipe', function () {
    $user = User::factory()->withTeam()->create();
    $original = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Original Chocolate Cake',
        'ingredients' => 'Flour, Sugar, Eggs',
        'instructions' => 'Mix and bake',
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.index')
        ->assertSee('Original Chocolate Cake')
        ->call('remix', $original->id)
        ->assertSee('Original Chocolate Cake')
        ->assertSee('Original Chocolate Cake (Remix)');

    $remix = Recipe::where('name', 'Original Chocolate Cake (Remix)')->first();

    expect($remix)->not->toBeNull()
        ->parent_id->toBe($original->id)
        ->team_id->toBe($user->current_team_id);
});
