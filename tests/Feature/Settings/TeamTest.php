<?php

use App\Models\TeamInvitation;
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

test('owner can invite a member by email', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.team')
        ->set('email', 'newmember@example.com')
        ->call('inviteMember')
        ->assertHasNoErrors()
        ->assertSet('email', '');

    expect($user->currentTeam->fresh()->invitations)->toHaveCount(1)
        ->and($user->currentTeam->fresh()->invitations->first()->email)->toEqual('newmember@example.com');
});

test('invite requires a valid email', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.team')
        ->set('email', 'not-an-email')
        ->call('inviteMember')
        ->assertHasErrors(['email' => 'email']);
});

test('cannot invite someone who is already a team member', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->currentTeam->users()->attach($member);

    Livewire::actingAs($owner)
        ->test('pages::settings.team')
        ->set('email', $member->email)
        ->call('inviteMember')
        ->assertHasErrors('email');
});

test('cannot invite someone who already has a pending invitation', function () {
    $user = User::factory()->withTeam()->create();

    TeamInvitation::factory()->for($user->currentTeam)->create(['email' => 'invited@example.com']);

    Livewire::actingAs($user)
        ->test('pages::settings.team')
        ->set('email', 'invited@example.com')
        ->call('inviteMember')
        ->assertHasErrors(['email' => 'unique']);
});

test('members table shows existing members and pending invitations', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->currentTeam->users()->attach($member);

    TeamInvitation::factory()->for($owner->currentTeam)->create(['email' => 'pending@example.com']);

    Livewire::actingAs($owner)
        ->test('pages::settings.team')
        ->assertSee($owner->name)
        ->assertSee($owner->email)
        ->assertSee($member->name)
        ->assertSee($member->email)
        ->assertSee('pending@example.com')
        ->assertSee('Member')
        ->assertSee('Invited');
});
