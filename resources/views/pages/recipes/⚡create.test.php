<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

test('can view page', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('recipes.create'))
        ->assertOk();
});

test('can create a recipe', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.create')
        ->set('form.name', 'Chocolate Cake')
        ->set('form.servings', '8 people')
        ->set('form.prep_time', '20 min')
        ->set('form.cook_time', '40 min')
        ->set('form.total_time', '1 hour')
        ->set('form.ingredients', "Flour\nSugar\nCocoa\nEggs")
        ->set('form.instructions', "Mix ingredients\nBake at 350F")
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.index'));

    expect(Recipe::firstWhere('name', 'Chocolate Cake'))->not->toBeNull()
        ->team_id->toBe($user->current_team_id)
        ->slug->toBe('chocolate-cake')
        ->servings->toBe('8 people')
        ->prep_time->toBe('20 min')
        ->cook_time->toBe('40 min')
        ->total_time->toBe('1 hour');
});
