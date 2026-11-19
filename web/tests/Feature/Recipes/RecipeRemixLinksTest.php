<?php

use App\Actions\Recipes\CopyRecipeToTeam;
use App\Enums\TeamRole;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;

test('remix links point at the team that owns each recipe', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($otherTeam, ['role' => TeamRole::Member]);
    $original = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);
    $copy = (new CopyRecipeToTeam)->handle($original, $otherTeam);

    $this->actingAs($user)
        ->get(route('recipes.show', ['current_team' => $user->currentTeam, 'recipe' => $original]))
        ->assertOk()
        ->assertSee(route('recipes.show', ['current_team' => $otherTeam, 'recipe' => $copy]));

    $this->actingAs($user)
        ->get(route('recipes.show', ['current_team' => $otherTeam, 'recipe' => $copy]))
        ->assertOk()
        ->assertSee('Remixed from')
        ->assertSee(route('recipes.show', ['current_team' => $original->team, 'recipe' => $original]));
});
