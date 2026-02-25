<?php

use App\Listeners\AcceptPendingInvitation;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Login;

test('listener accepts pending invitation on login', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $zoro = User::factory()->withTeam()->create(['name' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => $zoro->email]);

    session(['team_invitation_id' => $invitation->id]);

    $listener = new AcceptPendingInvitation;
    $listener->handle(new Login('web', $zoro, false));

    expect($luffy->currentTeam->fresh()->users->pluck('id'))->toContain($zoro->id)
        ->and(TeamInvitation::find($invitation->id))->toBeNull()
        ->and(session()->has('team_invitation_id'))->toBeFalse();
});

test('listener does nothing without session key', function () {
    $zoro = User::factory()->withTeam()->create(['name' => 'zoro@strawhat.pirates']);

    $listener = new AcceptPendingInvitation;
    $listener->handle(new Login('web', $zoro, false));

    expect(session()->has('team_invitation_id'))->toBeFalse();
});

test('listener handles deleted invitation gracefully', function () {
    $zoro = User::factory()->withTeam()->create(['name' => 'zoro@strawhat.pirates']);

    session(['team_invitation_id' => 999]);

    $listener = new AcceptPendingInvitation;
    $listener->handle(new Login('web', $zoro, false));

    expect(session()->has('team_invitation_id'))->toBeFalse();
});

test('listener handles email mismatch gracefully', function () {
    $luffy = User::factory()->withTeam()->create(['name' => 'luffy@strawhat.pirates']);
    $zoro = User::factory()->withTeam()->create(['name' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'nami@strawhat.pirates']);

    session(['team_invitation_id' => $invitation->id]);

    $listener = new AcceptPendingInvitation;
    $listener->handle(new Login('web', $zoro, false));

    expect($luffy->currentTeam->fresh()->users->pluck('id'))->not->toContain($zoro->id)
        ->and(session()->has('team_invitation_id'))->toBeFalse();
});
