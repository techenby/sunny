<?php

use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('renders successfully', function () {
    $user = User::factory()->withTeam()->create(['name' => 'Monkey D. Luffy']);

    Livewire::actingAs($user)
        ->test('team-switcher')
        ->assertOk()
        ->assertSee('Monkey D. Luffy\'s Team');
});

test('can switch teams', function () {
    $user = User::factory()
        ->withTeam('Baroque Works')
        ->has(
            Team::factory()->state(['name' => 'Strawhat Pirates'])
        )
        ->create(['name' => 'Nico Robin']);

    expect($user->current_team_id, $baroque = $user->teams->firstWhere('name', 'Baroque Works'));

    Livewire::actingAs($user)
        ->test('team-switcher')
        ->assertOk()
        ->assertSee('Baroque Works')
        ->assertSee('Strawhat Pirates')
        ->assertSet('currentTeamId', $baroque->id)
        ->set('currentTeamId', $user->teams->firstWhere('name', 'Strawhat Pirates')->id);
});

test('can create new team', function () {

})->todo();
