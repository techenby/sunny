<?php

use App\Models\User;
use Livewire\Livewire;

test('team settings page is displayed for team owners', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('team.edit'))
        ->assertOk();
});

test('team name can be updated by team owner', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.team')
        ->set('name', 'Updated Team Name')
        ->call('updateTeamName')
        ->assertHasNoErrors()
        ->assertDispatched('team-updated');

    expect($user->currentTeam->fresh()->name)->toEqual('Updated Team Name');
});

test('non-owners get a 403 when attempting to render the team settings page', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->currentTeam->users()->attach($member);
    $member->switchTeam($owner->currentTeam);

    $this->actingAs($member)
        ->get(route('team.edit'))
        ->assertForbidden();
});
