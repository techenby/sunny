<?php

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

    expect($this->crew->invitations()->where('email', 'zoro@strawhat.pirates')->whereNull('accepted_at')->exists())->toBeTrue();
    Notification::assertSentTo(new AnonymousNotifiable, TeamInvitationNotification::class);
});

test('someone who left the team can be invited again', function (string $state, string $oldEmail) {
    $old = TeamInvitation::factory()->for($this->crew)->{$state}()->create(['email' => $oldEmail]);

    Livewire::actingAs($this->nami)
        ->test('pages::teams.invite-member-modal', ['team' => $this->crew])
        ->set('inviteEmail', 'zoro@strawhat.pirates')
        ->call('createInvitation')
        ->assertHasNoErrors();

    $invitations = $this->crew->invitations()->whereRaw('LOWER(email) = ?', ['zoro@strawhat.pirates'])->get();

    expect($old->fresh())->toBeNull()
        ->and($invitations)->toHaveCount(1)
        ->and($invitations->first()->accepted_at)->toBeNull()
        ->and($invitations->first()->isExpired())->toBeFalse();
})->with([
    'accepted' => ['accepted', 'zoro@strawhat.pirates'],
    'expired' => ['expired', 'zoro@strawhat.pirates'],
    'accepted with different casing' => ['accepted', 'Zoro@StrawHat.Pirates'],
]);

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
