<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->luffy = User::factory()->create(['name' => 'Luffy']);
    $this->nami = User::factory()->create(['name' => 'Nami']);
    $this->zoro = User::factory()->create(['name' => 'Zoro']);
    $this->crew = Team::factory()->create(['name' => 'Straw Hats']);
    $this->crew->memberships()->create(['user_id' => $this->luffy->id, 'role' => TeamRole::Owner]);
    $this->crew->memberships()->create(['user_id' => $this->nami->id, 'role' => TeamRole::Admin]);
    $this->crew->memberships()->create(['user_id' => $this->zoro->id, 'role' => TeamRole::Member]);
});

test('guests cannot manage invitations', function () {
    $this->getJson(route('api.teams.invitations.index', 'household'))->assertUnauthorized();
    $this->postJson(route('api.teams.invitations.store', 'household'), ['email' => 'usopp@strawhat.pirates'])->assertUnauthorized();
    $this->deleteJson(route('api.teams.invitations.destroy', ['household', 'code']))->assertUnauthorized();
});

test('team members can see invitations that have not been accepted', function () {
    $pending = TeamInvitation::factory()->for($this->crew)->create(['email' => 'usopp@strawhat.pirates', 'role' => TeamRole::Admin]);
    TeamInvitation::factory()->for($this->crew)->accepted()->create();
    TeamInvitation::factory()->create();

    $this->actingAs($this->zoro)
        ->getJson(route('api.teams.invitations.index', $this->crew))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', $pending->code)
        ->assertJsonPath('data.0.email', 'usopp@strawhat.pirates')
        ->assertJsonPath('data.0.role', 'admin');
});

test('people outside the team cannot see its invitations', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('api.teams.invitations.index', $this->crew))
        ->assertForbidden();
});

test('owners and admins can invite someone by email', function (string $user) {
    $this->actingAs($this->{$user})
        ->postJson(route('api.teams.invitations.store', $this->crew), ['email' => 'usopp@strawhat.pirates', 'role' => 'admin'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'usopp@strawhat.pirates')
        ->assertJsonPath('data.role', 'admin');

    $invitation = $this->crew->invitations()->sole();

    expect($invitation)
        ->email->toBe('usopp@strawhat.pirates')
        ->role->toBe(TeamRole::Admin)
        ->invited_by->toBe($this->{$user}->id)
        ->expires_at->toBeInstanceOf(DateTimeInterface::class);
    Notification::assertSentTo(new AnonymousNotifiable, TeamInvitationNotification::class, fn ($notification) => $notification->invitation->is($invitation));
})->with(['luffy', 'nami']);

test('members cannot invite anyone', function () {
    $this->actingAs($this->zoro)
        ->postJson(route('api.teams.invitations.store', $this->crew), ['email' => 'usopp@strawhat.pirates', 'role' => 'member'])
        ->assertForbidden();

    expect($this->crew->invitations()->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('people outside the team cannot invite to it', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('api.teams.invitations.store', $this->crew), ['email' => 'usopp@strawhat.pirates', 'role' => 'member'])
        ->assertForbidden();
});

test('invitations are validated like the web', function (Closure $payload, string $error) {
    TeamInvitation::factory()->for($this->crew)->create(['email' => 'robin@strawhat.pirates']);

    $this->actingAs($this->luffy)
        ->postJson(route('api.teams.invitations.store', $this->crew), $payload($this))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    Notification::assertNothingSent();
})->with([
    'missing email' => [fn () => ['role' => 'member'], 'email'],
    'invalid email' => [fn () => ['email' => 'not-an-email', 'role' => 'member'], 'email'],
    'already a member' => [fn ($test) => ['email' => strtoupper($test->zoro->email), 'role' => 'member'], 'email'],
    'already invited' => [fn () => ['email' => 'Robin@StrawHat.Pirates', 'role' => 'member'], 'email'],
    'owner role' => [fn () => ['email' => 'usopp@strawhat.pirates', 'role' => 'owner'], 'role'],
    'unknown role' => [fn () => ['email' => 'usopp@strawhat.pirates', 'role' => 'captain'], 'role'],
]);

test('an expired invitation is replaced by a new one', function () {
    $expired = TeamInvitation::factory()->for($this->crew)->expired()->create(['email' => 'usopp@strawhat.pirates']);

    $this->actingAs($this->luffy)
        ->postJson(route('api.teams.invitations.store', $this->crew), ['email' => 'usopp@strawhat.pirates', 'role' => 'member'])
        ->assertCreated();

    expect($expired->fresh())->toBeNull()
        ->and($this->crew->invitations()->count())->toBe(1);
});

test('owners and admins can cancel an invitation', function (string $user) {
    $invitation = TeamInvitation::factory()->for($this->crew)->create();

    $this->actingAs($this->{$user})
        ->deleteJson(route('api.teams.invitations.destroy', [$this->crew, $invitation]))
        ->assertNoContent();

    expect($invitation->fresh())->toBeNull();
})->with(['luffy', 'nami']);

test('members cannot cancel an invitation', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create();

    $this->actingAs($this->zoro)
        ->deleteJson(route('api.teams.invitations.destroy', [$this->crew, $invitation]))
        ->assertForbidden();

    expect($invitation->fresh())->not->toBeNull();
});

test('another team\'s invitation cannot be cancelled through this team', function () {
    $invitation = TeamInvitation::factory()->for($this->luffy->personalTeam())->create();

    $this->actingAs($this->nami)
        ->deleteJson(route('api.teams.invitations.destroy', [$this->crew, $invitation]))
        ->assertNotFound();

    expect($invitation->fresh())->not->toBeNull();
});
