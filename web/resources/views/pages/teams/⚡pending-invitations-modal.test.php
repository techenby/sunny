<?php

use App\Enums\TeamRole;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('the dashboard shows the pending invitations modal', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    actingAs($zoro)
        ->get(route('dashboard', $zoro->currentTeam))
        ->assertOk()
        ->assertSeeLivewire('pages::teams.pending-invitations-modal');
});

test('it lists pending invitations for the user email', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create(['email' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'Zoro@StrawHat.Pirates']);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->assertSee($luffy->currentTeam->name)
        ->assertSee("{$invitation->inviter->name} invited you to join this team.");
});

test('it hides expired, accepted, and other people\'s invitations', function () {
    $luffy = User::factory()->create();
    $nami = User::factory()->create();
    $zoro = User::factory()->create();
    $expired = TeamInvitation::factory()->for($luffy->currentTeam)->expired()->create(['email' => $zoro->email]);
    TeamInvitation::factory()->for($nami->currentTeam)->accepted()->create(['email' => $zoro->email]);
    TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'usopp@strawhat.pirates']);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->assertDontSeeHtml('data-test="pending-invitations-modal"');

    expect($expired->fresh())->not->toBeNull();
});

test('accepting an invitation joins and switches to the team', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->call('acceptInvitation', $invitation->code)
        ->assertRedirect(route('dashboard', $luffy->currentTeam));

    expect($luffy->currentTeam->fresh()->members->pluck('id'))->toContain($zoro->id)
        ->and($zoro->fresh()->current_team_id)->toBe($luffy->currentTeam->id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and(session('team-invitation-accepted'))->toBeTrue();
});

test('accepting an invitation assigns its role', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create([
        'email' => $zoro->email,
        'role' => TeamRole::Admin,
    ]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->call('acceptInvitation', $invitation->code);

    expect($zoro->teamRole($luffy->currentTeam))->toBe(TeamRole::Admin);
});

test('accepting an invitation to a team the user already belongs to keeps one membership', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $luffy->currentTeam->memberships()->create(['user_id' => $zoro->id, 'role' => TeamRole::Member]);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->call('acceptInvitation', $invitation->code)
        ->assertHasNoErrors();

    expect($luffy->currentTeam->memberships()->where('user_id', $zoro->id)->count())->toBe(1)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('an invitation sent to another email cannot be accepted', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'nami@strawhat.pirates']);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->call('acceptInvitation', $invitation->code)
        ->assertHasErrors('invitation');

    expect($zoro->fresh()->belongsToTeam($luffy->currentTeam))->toBeFalse();
});

test('an expired invitation cannot be accepted', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->expired()->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->call('acceptInvitation', $invitation->code)
        ->assertHasErrors('invitation');
});

test('an accepted invitation cannot be accepted again', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->accepted()->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->call('acceptInvitation', $invitation->code)
        ->assertNotFound();
});

test('declining an invitation deletes it', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->call('declineInvitation', $invitation->code)
        ->assertDispatched('toast-show');

    expect($invitation->fresh())->toBeNull()
        ->and($zoro->fresh()->belongsToTeam($luffy->currentTeam))->toBeFalse();
});

test('it shows a toast after an invitation is accepted', function () {
    $zoro = User::factory()->create();

    session()->flash('team-invitation-accepted', true);

    Livewire::actingAs($zoro)
        ->test('pages::teams.pending-invitations-modal')
        ->assertDispatched('toast-show');
});

test('invitations can no longer be accepted with a GET request', function () {
    $luffy = User::factory()->create();
    $zoro = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    actingAs($zoro)
        ->get("/invitations/{$invitation->code}/accept")
        ->assertNotFound();

    expect($invitation->fresh()->accepted_at)->toBeNull();
});

test('the daily cleanup deletes expired invitations', function () {
    Date::setTestNow(today());

    $luffy = User::factory()->create();
    $expired = TeamInvitation::factory()->for($luffy->currentTeam)->expired()->create();
    $unexpired = TeamInvitation::factory()->for($luffy->currentTeam)->create(['expires_at' => now()->addDay()]);
    $withoutExpiration = TeamInvitation::factory()->for($luffy->currentTeam)->create(['expires_at' => null]);

    $this->artisan('schedule:run')->assertSuccessful();

    expect($expired->fresh())->toBeNull()
        ->and($unexpired->fresh())->not->toBeNull()
        ->and($withoutExpiration->fresh())->not->toBeNull();
});
