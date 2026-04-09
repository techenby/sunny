<?php

use App\Enums\TeamRole;
use App\Models\TeamInvitation;
use App\Models\User;
use Livewire\Livewire;

test('authenticated user can accept invitation', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.accept-invitation', ['invitation' => $invitation])
        ->assertRedirect(route('dashboard'));

    expect($luffy->currentTeam->fresh()->members->pluck('id'))->toContain($zoro->id)
        ->and($zoro->fresh()->current_team_id)->toBe($luffy->currentTeam->id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('cannot accept invitation for wrong email', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'nami@strawhat.pirates']);

    Livewire::actingAs($zoro)
        ->test('pages::teams.accept-invitation', ['invitation' => $invitation])
        ->assertHasErrors('invitation');
});

test('cannot accept already accepted invitation', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->accepted()->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.accept-invitation', ['invitation' => $invitation])
        ->assertHasErrors('invitation');
});

test('cannot accept expired invitation', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->expired()->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.accept-invitation', ['invitation' => $invitation])
        ->assertHasErrors('invitation');
});

test('invitation assigns the correct role', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create([
        'email' => $zoro->email,
        'role' => TeamRole::Admin,
    ]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.accept-invitation', ['invitation' => $invitation])
        ->assertRedirect(route('dashboard'));

    expect($zoro->teamRole($luffy->currentTeam))->toBe(TeamRole::Admin);
});

test('already a member gracefully handles duplicate join', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $luffy->currentTeam->memberships()->create(['user_id' => $zoro->id, 'role' => TeamRole::Member]);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.accept-invitation', ['invitation' => $invitation])
        ->assertRedirect(route('dashboard'));

    expect($zoro->fresh()->current_team_id)->toBe($luffy->currentTeam->id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('unauthenticated user is redirected to login', function () {
    $luffy = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'zoro@strawhat.pirates']);

    $this->get(route('invitations.accept', $invitation))
        ->assertRedirect(route('login'));
});
