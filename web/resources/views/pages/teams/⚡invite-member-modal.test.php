<?php

use App\Actions\Teams\AcceptTeamInvitation;
use App\Actions\Teams\LeaveTeam;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->nami = User::factory()->create();
    $this->crew = Team::factory()->create(['name' => 'Thousand Sunny Crew']);
    $this->crew->members()->attach($this->nami, ['role' => TeamRole::Owner->value]);
});

test('owners can invite someone to the team', function () {
    Livewire::actingAs($this->nami)
        ->test('pages::teams.invite-member-modal', ['team' => $this->crew])
        ->set('inviteEmail', 'zoro@strawhat.pirates')
        ->call('createInvitation')
        ->assertHasNoErrors();

    expect($this->crew->invitations()->where('email', 'zoro@strawhat.pirates')->exists())->toBeTrue();
    Notification::assertSentTo(new AnonymousNotifiable, TeamInvitationNotification::class);
});

test('an expired invitation is replaced by a new one', function (string $oldEmail) {
    $old = TeamInvitation::factory()->for($this->crew)->expired()->create(['email' => $oldEmail]);

    Livewire::actingAs($this->nami)
        ->test('pages::teams.invite-member-modal', ['team' => $this->crew])
        ->set('inviteEmail', 'zoro@strawhat.pirates')
        ->call('createInvitation')
        ->assertHasNoErrors();

    $invitations = $this->crew->invitations()->whereRaw('LOWER(email) = ?', ['zoro@strawhat.pirates'])->get();

    expect($old->fresh())->toBeNull()
        ->and($invitations)->toHaveCount(1)
        ->and($invitations->first()->isExpired())->toBeFalse();
})->with([
    'same casing' => ['zoro@strawhat.pirates'],
    'different casing' => ['Zoro@StrawHat.Pirates'],
]);

test('someone who accepted an invitation and left the team can be invited again', function () {
    $zoro = User::factory()->create(['email' => 'zoro@strawhat.pirates']);
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);
    resolve(AcceptTeamInvitation::class)->handle($zoro, $invitation);
    resolve(LeaveTeam::class)->handle($zoro, $this->crew);

    Livewire::actingAs($this->nami)
        ->test('pages::teams.invite-member-modal', ['team' => $this->crew])
        ->set('inviteEmail', 'zoro@strawhat.pirates')
        ->call('createInvitation')
        ->assertHasNoErrors();

    expect($this->crew->invitations()->where('email', 'zoro@strawhat.pirates')->count())->toBe(1);
});

test('a pending invitation blocks a second one', function () {
    $pending = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);

    Livewire::actingAs($this->nami)
        ->test('pages::teams.invite-member-modal', ['team' => $this->crew])
        ->set('inviteEmail', 'zoro@strawhat.pirates')
        ->call('createInvitation')
        ->assertHasErrors('inviteEmail');

    expect($pending->fresh())->not->toBeNull();
    Notification::assertNothingSent();
});

test('nobody can be invited as an owner', function () {
    Livewire::actingAs($this->nami)
        ->test('pages::teams.invite-member-modal', ['team' => $this->crew])
        ->set('inviteEmail', 'zoro@strawhat.pirates')
        ->set('inviteRole', 'owner')
        ->call('createInvitation')
        ->assertHasErrors('inviteRole');

    expect($this->crew->invitations()->exists())->toBeFalse();
    Notification::assertNothingSent();
});
