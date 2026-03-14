<?php

use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
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
    Notification::fake();

    $user = User::factory()->withTeam()->create(['name' => 'Monkey D. Luffy']);

    Livewire::actingAs($user)
        ->test('pages::settings.team')
        ->set('email', 'zoro@strawhat.pirates')
        ->call('inviteMember')
        ->assertHasNoErrors()
        ->assertSet('email', '');

    $invitations = $user->currentTeam->fresh()->invitations;
    expect($invitations)->toHaveCount(1)
        ->and($invitations->first()->email)->toEqual('zoro@strawhat.pirates');

    Notification::assertSentOnDemand(TeamInvitationNotification::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'zoro@strawhat.pirates';
    });
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

test('owner can cancel a pending invitation', function () {
    $owner = User::factory()->withTeam()->create();
    $invitation = TeamInvitation::factory()->for($owner->currentTeam)->create(['email' => 'pending@example.com']);

    Livewire::actingAs($owner)
        ->test('pages::settings.team')
        ->call('cancelInvitation', $invitation->id)
        ->assertHasNoErrors();

    expect(TeamInvitation::find($invitation->id))->toBeNull();
});

test('non-owner cannot cancel a pending invitation', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->currentTeam->users()->attach($member);
    $member->switchTeam($owner->currentTeam);

    $invitation = TeamInvitation::factory()->for($owner->currentTeam)->create(['email' => 'pending@example.com']);

    Livewire::actingAs($member)
        ->test('pages::settings.team')
        ->assertStatus(403);
});

test('owner can copy invitation link', function () {
    $owner = User::factory()->withTeam()->create();
    $invitation = TeamInvitation::factory()->for($owner->currentTeam)->create(['email' => 'pending@example.com']);

    Livewire::actingAs($owner)
        ->test('pages::settings.team')
        ->call('copyInvitationLink', $invitation->id)
        ->assertDispatched('copy-to-clipboard', fn ($name, $params) => str_contains($params['url'], '/invitations/' . $invitation->id . '/accept'));
});

test('non-owner cannot copy invitation link', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->currentTeam->users()->attach($member);
    $member->switchTeam($owner->currentTeam);

    $invitation = TeamInvitation::factory()->for($owner->currentTeam)->create(['email' => 'pending@example.com']);

    Livewire::actingAs($member)
        ->test('pages::settings.team')
        ->assertStatus(403);
});

test('cannot copy invitation link belonging to another team', function () {
    $owner = User::factory()->withTeam()->create();
    $otherOwner = User::factory()->withTeam()->create();
    $invitation = TeamInvitation::factory()->for($otherOwner->currentTeam)->create(['email' => 'pending@example.com']);

    Livewire::actingAs($owner)
        ->test('pages::settings.team')
        ->call('copyInvitationLink', $invitation->id);
})->throws(ModelNotFoundException::class);

test('cannot cancel invitation belonging to another team', function () {
    $owner = User::factory()->withTeam()->create();
    $otherOwner = User::factory()->withTeam()->create();
    $invitation = TeamInvitation::factory()->for($otherOwner->currentTeam)->create(['email' => 'pending@example.com']);

    Livewire::actingAs($owner)
        ->test('pages::settings.team')
        ->call('cancelInvitation', $invitation->id);
})->throws(ModelNotFoundException::class);

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
