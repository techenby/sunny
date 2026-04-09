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

    $user = User::factory()->create();

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

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam')
        ->assertRedirect();

    expect(Recipe::firstWhere('team_id', $user->currentTeam->id))->not->toBeNull()
        ->name->toBe('Shared Pasta')
        ->description->toBe('A nice pasta recipe')
        ->servings->toBe('4')
        ->source->toBe(route('recipes.shared', $recipe->share_token))
        ->share_token->toBeNull();
});

test('owner sees disabled add button', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->shared()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSeeHtml('wire:click="addToMyTeam" disabled');
});

test('previously added recipe shows view in my recipes', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Shared Pasta']);
    $user = User::factory()->create();

    $copy = Recipe::factory()->for($user->currentTeam)->create([
        'source' => route('recipes.shared', $recipe->share_token),
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->assertOk()
        ->assertSee('Add to My Team')
        ->call('addToMyTeam')
        ->assertRedirect(route('recipes.show', ['recipe' => $copy]));

    expect(Recipe::where('team_id', $user->currentTeam->id)->where('source', route('recipes.shared', $recipe->share_token))->count())->toBe(1);
});

test('adding recipe that already exists redirects to existing', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Shared Pasta']);
    $user = User::factory()->create();

    $existing = Recipe::factory()->for($user->currentTeam)->create([
        'source' => route('recipes.shared', $recipe->share_token),
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam')
        ->assertRedirect(route('recipes.show', $existing));

    expect(Recipe::where('team_id', $user->currentTeam->id)->count())->toBe(1);
});
