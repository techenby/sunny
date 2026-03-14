<?php

use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('can view a recipe', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Pasta Carbonara',
        'ingredients' => 'Pasta, Eggs, Bacon',
        'instructions' => 'Cook pasta, mix with eggs and bacon',
    ]);

    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Pasta Carbonara')
        ->assertSee('Pasta, Eggs, Bacon');
});

test('cannot view a recipe from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherRecipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->get(route('recipes.show', $otherRecipe))
        ->assertForbidden();
});

test('can create a remix of a recipe', function () {
    $user = User::factory()->withTeam()->create();
    $original = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Original Chocolate Cake',
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.show', ['recipe' => $original])
        ->assertSee('Original Chocolate Cake')
        ->call('remix')
        ->assertRedirect();

    $remix = Recipe::where('name', 'Original Chocolate Cake (Remix)')->first();

    expect($remix)->not->toBeNull()
        ->parent_id->toBe($original->id)
        ->team_id->toBe($user->current_team_id);
});

test('remix shows parent recipe', function () {
    $user = User::factory()->withTeam()->create();
    $original = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Original Recipe']);
    $remix = Recipe::factory()->for($user->currentTeam)->remixOf($original)->create(['name' => 'Remixed Recipe']);

    $this->actingAs($user)
        ->get(route('recipes.show', $remix))
        ->assertOk()
        ->assertSee('Remixed From')
        ->assertSee('Original Recipe');
});

test('parent shows remixes', function () {
    $user = User::factory()->withTeam()->create();
    $original = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Original Recipe']);
    Recipe::factory()->for($user->currentTeam)->remixOf($original)->create(['name' => 'Remixed Recipe']);

    $this->actingAs($user)
        ->get(route('recipes.show', $original))
        ->assertOk()
        ->assertSee('Remixes')
        ->assertSee('Remixed Recipe');
});

test('shows tags on recipe', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'description' => 'A delicious recipe',
        'tags' => ['dinner', 'italian'],
    ]);

    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('dinner')
        ->assertSee('italian');
});

test('displays photo when recipe has one', function () {
    Storage::fake();

    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Chocolate Cake',
        'photo_path' => "teams/{$user->current_team_id}/recipes/chocolate-cake.png",
    ]);

    Storage::put($recipe->photo_path, 'photo contents');

    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('<img', false);
});

test('does not display photo when recipe has none', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Chocolate Cake',
        'photo_path' => null,
    ]);

    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertDontSee('<img');
});

test('can delete a recipe from show page', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.show', ['recipe' => $recipe])
        ->call('delete')
        ->assertRedirect(route('recipes.index'));

    expect($recipe->fresh())->toBeNull();
});

test('non-owner cannot delete a recipe from show page', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.show', ['recipe' => $recipe])
        ->call('delete')
        ->assertForbidden();
});

describe('copy to team feature', function () {
    test('can copy recipe to another team from show page', function () {
        $otherTeam = Team::factory()->create();
        $user = User::factory()->withTeam()->hasAttached($otherTeam)->create();

        $recipe = Recipe::factory()->for($user->currentTeam)->create([
            'name' => 'Pasta Carbonara',
        ]);

        Livewire::actingAs($user)
            ->test('pages::recipes.show', ['recipe' => $recipe])
            ->set('copyToTeamId', $otherTeam->id)
            ->call('copyToTeam');

        $copy = $otherTeam->recipes()->where('name', 'Pasta Carbonara')->first();

        expect($copy)->not->toBeNull()
            ->parent_id->toBe($recipe->id)
            ->team_id->toBe($otherTeam->id)
            ->share_token->toBeNull();
    });

    test('cannot copy recipe to a team user does not belong to', function () {
        $user = User::factory()->withTeam()->create();
        $otherTeam = Team::factory()->create();

        $recipe = Recipe::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::recipes.show', ['recipe' => $recipe])
            ->set('copyToTeamId', $otherTeam->id)
            ->call('copyToTeam')
            ->assertHasErrors('copyToTeamId');
    });

    test('cannot copy recipe from team user does not belong to', function () {
        $otherTeam = Team::factory()->create();
        $user = User::factory()->withTeam()->hasAttached($otherTeam)->create();

        $recipe = Recipe::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::recipes.show', ['recipe' => $recipe])
            ->set('copyToTeamId', $otherTeam->id)
            ->call('copyToTeam')
            ->assertForbidden();
    });
});

describe('sharing feature', function () {
    test('can toggle sharing on recipe from team user does belong to', function () {
        $user = User::factory()->withTeam()->create();
        $recipe = Recipe::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user);

        Livewire::test('pages::recipes.show', ['recipe' => $recipe])
            ->call('toggleSharing');

        expect($recipe->fresh()->isShared())->toBeTrue();

        Livewire::test('pages::recipes.show', ['recipe' => $recipe])
            ->call('toggleSharing');

        expect($recipe->fresh()->isShared())->toBeFalse();
    });

    test('cannot toggle sharing on recipe from team user does not belong to', function () {
        $user = User::factory()->withTeam()->create();
        $recipe = Recipe::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::recipes.show', ['recipe' => $recipe])
            ->call('toggleSharing')
            ->assertForbidden();
    });
});
