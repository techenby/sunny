<?php

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('existing user can accept invitation', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $zoro = User::factory()->create(['name' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    $acceptUrl = URL::signedRoute('invitation.accept', $invitation);

    $this->actingAs($zoro)
        ->get($acceptUrl)
        ->assertRedirect(route('dashboard'));

    expect($luffy->currentTeam->fresh()->users->pluck('id'))->toContain($zoro->id)
        ->and($zoro->fresh()->current_team_id)->toBe($luffy->currentTeam->id)
        ->and(TeamInvitation::find($invitation->id))->toBeNull();
});

test('cannot accept invitation for wrong email', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $zoro = User::factory()->create(['name' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'nami@strawhat.pirates']);

    $acceptUrl = URL::signedRoute('invitation.accept', $invitation);

    $this->actingAs($zoro)
        ->get($acceptUrl)
        ->assertForbidden();
});

test('unauthenticated user with existing account is redirected to login', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $zoro = User::factory()->create(['name' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    $acceptUrl = URL::signedRoute('invitation.accept', $invitation);

    $this->get($acceptUrl)
        ->assertRedirect(route('login'));

    expect(session('team_invitation_id'))->toBe($invitation->id);
});

test('unauthenticated user without account is redirected to register', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'zoro@strawhat.pirates']);

    $acceptUrl = URL::signedRoute('invitation.accept', $invitation);

    $this->get($acceptUrl)
        ->assertRedirect(route('register'));

    expect(session('team_invitation_id'))->toBe($invitation->id);
});

test('pending invitation is auto-accepted after registration', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'zoro@strawhat.pirates']);

    $this->withSession(['team_invitation_id' => $invitation->id])
        ->post(route('register'), [
            'name' => 'Roronoa Zoro',
            'email' => 'zoro@strawhat.pirates',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $zoro = User::where('email', 'zoro@strawhat.pirates')->first();

    expect($luffy->currentTeam->fresh()->users->pluck('id'))->toContain($zoro->id)
        ->and($zoro->current_team_id)->toBe($luffy->currentTeam->id)
        ->and(TeamInvitation::find($invitation->id))->toBeNull();
});

test('cannot register with different email', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'zoro@strawhat.pirates']);

    $this->withSession(['team_invitation_id' => $invitation->id])
        ->post(route('register'), [
            'name' => 'Roronoa Zoro',
            'email' => 'zoro@baroqueworks.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertInvalid(['email' => 'must match']);
});

test('pending invitation is auto-accepted after login', function () {
    $luffy = User::factory()->withTeam()->create(['email' => 'luffy@strawhat.pirates']);
    $zoro = User::factory()->withTeam()->create(['email' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    $this->withSession(['team_invitation_id' => $invitation->id])
        ->post(route('login'), [
            'email' => $zoro->email,
            'password' => 'password',
        ]);

    expect($luffy->currentTeam->fresh()->users->pluck('id'))->toContain($zoro->id)
        ->and($zoro->fresh()->current_team_id)->toBe($luffy->currentTeam->id)
        ->and(TeamInvitation::find($invitation->id))->toBeNull();
});

test('cannot login with different email than invitation', function () {
    $luffy = User::factory()->withTeam()->create(['email' => 'luffy@strawhat.pirates']);
    [$sanjiOld, $sanjiNew] = User::factory()
        ->count(2)
        ->sequence(
            ['email' => 'sanji@baratie.restaurant'],
            ['email' => 'sanji@strawhat.pirates'],
        )
        ->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $sanjiNew->email]);

    $this->withSession(['team_invitation_id' => $invitation->id])
        ->post(route('login'), [
            'email' => $sanjiOld->email,
            'password' => 'password',
        ])
        ->assertInvalid(['email' => 'must match']);
});

test('expired signed url returns 403', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $zoro = User::factory()->create(['name' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    $acceptUrl = URL::temporarySignedRoute('invitation.accept', now()->subDay(), $invitation);

    $this->actingAs($zoro)
        ->get($acceptUrl)
        ->assertForbidden();
});

test('already a member gracefully handles duplicate join', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $zoro = User::factory()->create(['name' => 'zoro@strawhat.pirates']);
    $luffy->currentTeam->users()->attach($zoro);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    $acceptUrl = URL::signedRoute('invitation.accept', $invitation);

    $this->actingAs($zoro)
        ->get($acceptUrl)
        ->assertRedirect(route('dashboard'));

    expect(TeamInvitation::find($invitation->id))->toBeNull();
});

test('tampered signed url returns 403', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'zoro@strawhat.pirates']);

    $acceptUrl = URL::signedRoute('invitation.accept', $invitation);

    $this->get($acceptUrl . '&tampered=true')
        ->assertForbidden();
});
