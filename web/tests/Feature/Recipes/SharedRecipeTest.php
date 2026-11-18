<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

test('a shared recipe can be added to the current team once', function () {
    $user = User::factory()->create();
    Recipe::factory()->create(['name' => 'Grandma’s Pie', 'share_token' => 'abc']);

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => 'abc'])
        ->call('addToMyTeam')
        ->call('addToMyTeam');

    expect($user->currentTeam->recipes()->sole())
        ->name->toBe('Grandma’s Pie')
        ->source->toBe(route('recipes.shared', 'abc'));
});

test('a shared recipe can be added to a team that deleted one with the same name', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Grandma’s Pie'])->delete();
    Recipe::factory()->create(['name' => 'Grandma’s Pie', 'share_token' => 'abc']);

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => 'abc'])
        ->call('addToMyTeam')
        ->assertHasNoErrors();

    expect($user->currentTeam->recipes()->sole())->slug->toBe('grandmas-pie-1');
});
