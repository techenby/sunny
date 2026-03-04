<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

test('shared recipe is viewable without authentication', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Test Recipe']);

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('Test Recipe');
});

test('invalid share token returns 404', function () {
    $this->get(route('recipes.shared', 'nonexistent-token'))
        ->assertNotFound();
});

test('unshared recipe is not publicly accessible', function () {
    $recipe = Recipe::factory()->create();

    expect($recipe->share_token)->toBeNull();

    $this->get('/recipes/shared/null')
        ->assertNotFound();
});

test('authenticated user can add shared recipe to their team', function () {
    $recipe = Recipe::factory()->shared()->create([
        'name' => 'Grandma Cookies',
        'description' => 'Delicious cookies',
        'ingredients' => '<ul><li>Flour</li></ul>',
        'instructions' => '<ol><li>Mix</li></ol>',
    ]);

    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('Add to My Team');
});

test('guest does not see add to my team button', function () {
    $recipe = Recipe::factory()->shared()->create();

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertDontSee('Add to My Team');
});

test('adding shared recipe to team creates a copy', function () {
    $recipe = Recipe::factory()->shared()->create([
        'name' => 'Shared Pasta',
        'description' => 'A nice pasta recipe',
        'servings' => '4',
    ]);

    $user = User::factory()->withTeam()->create();

    $this->actingAs($user);

    Livewire::test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam')
        ->assertRedirect();

    $copy = Recipe::where('team_id', $user->currentTeam->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->name)->toBe('Shared Pasta')
        ->and($copy->description)->toBe('A nice pasta recipe')
        ->and($copy->servings)->toBe('4')
        ->and($copy->source)->toBe(route('recipes.shared', $recipe->share_token))
        ->and($copy->share_token)->toBeNull();
});

test('owner sees already in my recipes instead of add button', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->shared()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('Already in My Recipes')
        ->assertDontSee('Add to My Team');
});

test('previously added recipe shows already in my recipes', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Shared Pasta']);
    $user = User::factory()->withTeam()->create();

    Recipe::factory()->for($user->currentTeam)->create([
        'source' => route('recipes.shared', $recipe->share_token),
    ]);

    $this->actingAs($user)
        ->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('Already in My Recipes')
        ->assertDontSee('Add to My Team');
});

test('adding recipe that already exists redirects to existing', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Shared Pasta']);
    $user = User::factory()->withTeam()->create();

    $existing = Recipe::factory()->for($user->currentTeam)->create([
        'source' => route('recipes.shared', $recipe->share_token),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam')
        ->assertRedirect(route('recipes.show', $existing));

    expect(Recipe::where('team_id', $user->currentTeam->id)->count())->toBe(1);
});
