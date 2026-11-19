<?php

use App\Actions\Teams\AcceptTeamInvitation;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function answerInvitationAfterLookup(Closure $competingRequest): void
{
    $answered = false;

    DB::listen(function (QueryExecuted $query) use (&$answered, $competingRequest): void {
        if ($answered || ! str_contains($query->sql, 'from "team_invitations" where "code"')) {
            return;
        }

        $answered = true;
        $competingRequest();
    });
}

beforeEach(function () {
    $this->luffy = User::factory()->create(['name' => 'Luffy']);
    $this->zoro = User::factory()->create(['name' => 'Zoro', 'email' => 'zoro@strawhat.pirates']);
    $this->crew = Team::factory()->create(['name' => 'Straw Hats']);
    $this->crew->memberships()->create(['user_id' => $this->luffy->id, 'role' => TeamRole::Owner]);
});

test('guests cannot see or answer invitations', function () {
    $this->getJson(route('api.invitations.index'))->assertUnauthorized();
    $this->postJson(route('api.invitations.accept', 'code'))->assertUnauthorized();
    $this->postJson(route('api.invitations.decline', 'code'))->assertUnauthorized();
});

test('users must verify their email before seeing or answering an invitation', function () {
    $this->zoro->markEmailAsUnverified();
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => $this->zoro->email]);

    $this->actingAs($this->zoro)->getJson(route('api.invitations.index'))->assertStatus(423);
    $this->postJson(route('api.invitations.accept', $invitation))->assertStatus(423)->assertJsonPath('email_verification_required', true);
    $this->postJson(route('api.invitations.decline', $invitation))->assertStatus(423);

    expect($invitation->fresh())->not->toBeNull()
        ->and($this->zoro->belongsToTeam($this->crew))->toBeFalse();
});

test('a user sees the pending invitations sent to their email', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'Zoro@StrawHat.Pirates', 'invited_by' => $this->luffy->id]);
    TeamInvitation::factory()->expired()->create(['email' => 'zoro@strawhat.pirates']);
    TeamInvitation::factory()->for($this->crew)->create(['email' => 'nami@strawhat.pirates']);

    $this->actingAs($this->zoro)
        ->getJson(route('api.invitations.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', $invitation->code)
        ->assertJsonPath('data.0.role', 'member')
        ->assertJsonPath('data.0.team', ['id' => $this->crew->id, 'name' => 'Straw Hats', 'slug' => $this->crew->slug])
        ->assertJsonPath('data.0.inviter', ['id' => $this->luffy->id, 'name' => 'Luffy']);
});

test('accepting an invitation joins the team with its role without changing the current team', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates', 'role' => TeamRole::Admin]);

    $this->actingAs($this->zoro)
        ->postJson(route('api.invitations.accept', $invitation))
        ->assertOk()
        ->assertJsonPath('data.id', $this->crew->id)
        ->assertJsonPath('data.members.1', ['id' => $this->zoro->id, 'name' => 'Zoro', 'initials' => 'Z', 'role' => 'admin']);

    expect($this->zoro->fresh())
        ->teamRole($this->crew)->toBe(TeamRole::Admin)
        ->current_team_id->toBe($this->zoro->personalTeam()->id)
        ->and($invitation->fresh())->toBeNull();
});

test('accepting an invitation to a team the user already belongs to keeps one membership', function () {
    $this->crew->memberships()->create(['user_id' => $this->zoro->id, 'role' => TeamRole::Member]);
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates', 'role' => TeamRole::Admin]);

    $this->actingAs($this->zoro)
        ->postJson(route('api.invitations.accept', $invitation))
        ->assertOk();

    expect($this->crew->memberships()->where('user_id', $this->zoro->id)->count())->toBe(1);
});

test('declining an invitation deletes it', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);

    $this->actingAs($this->zoro)
        ->postJson(route('api.invitations.decline', $invitation))
        ->assertNoContent();

    expect($invitation->fresh())->toBeNull()
        ->and($this->zoro->belongsToTeam($this->crew))->toBeFalse();
});

test('someone else\'s or an expired invitation cannot be answered', function (string $action, array $attributes) {
    $invitation = TeamInvitation::factory()->for($this->crew)->create($attributes);

    $this->actingAs($this->zoro)
        ->postJson(route("api.invitations.{$action}", $invitation))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invitation');

    expect($invitation->fresh())->not->toBeNull()
        ->and($this->zoro->belongsToTeam($this->crew))->toBeFalse();
})->with(['accept', 'decline'])->with([
    'someone else\'s' => [['email' => 'nami@strawhat.pirates']],
    'expired' => [['email' => 'zoro@strawhat.pirates', 'expires_at' => now()->subDay()]],
]);

test('an invitation that is gone cannot be answered by its code', function (string $action) {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);
    $invitation->delete();

    $this->actingAs($this->zoro)
        ->postJson(route("api.invitations.{$action}", $invitation))
        ->assertNotFound();
})->with(['accept', 'decline']);

test('accepting an invitation someone already accepted for the user succeeds without a second membership', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates', 'role' => TeamRole::Admin]);
    answerInvitationAfterLookup(fn () => resolve(AcceptTeamInvitation::class)->handle($this->zoro, $invitation->fresh()));

    $this->actingAs($this->zoro)
        ->postJson(route('api.invitations.accept', $invitation))
        ->assertOk()
        ->assertJsonPath('data.id', $this->crew->id);

    expect($this->crew->memberships()->where('user_id', $this->zoro->id)->count())->toBe(1)
        ->and($this->zoro->teamRole($this->crew))->toBe(TeamRole::Admin)
        ->and($invitation->fresh())->toBeNull();
});

test('accepting an invitation declined in the meantime is not found', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);
    answerInvitationAfterLookup(fn () => $invitation->delete());

    $this->actingAs($this->zoro)
        ->postJson(route('api.invitations.accept', $invitation))
        ->assertNotFound();

    expect($this->zoro->belongsToTeam($this->crew))->toBeFalse();
});

test('declining an invitation declined in the meantime succeeds', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);
    answerInvitationAfterLookup(fn () => $invitation->delete());

    $this->actingAs($this->zoro)
        ->postJson(route('api.invitations.decline', $invitation))
        ->assertNoContent();

    expect($invitation->fresh())->toBeNull();
});

test('declining an invitation accepted in the meantime succeeds and keeps the membership', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);
    answerInvitationAfterLookup(fn () => resolve(AcceptTeamInvitation::class)->handle($this->zoro, $invitation->fresh()));

    $this->actingAs($this->zoro)
        ->postJson(route('api.invitations.decline', $invitation))
        ->assertNoContent();

    expect($this->zoro->belongsToTeam($this->crew))->toBeTrue()
        ->and($invitation->fresh())->toBeNull();
});

test('the accept action re-checks the invitation instead of trusting the model it was given', function () {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);
    $stale = $invitation->fresh();
    $accept = resolve(AcceptTeamInvitation::class);

    $accept->handle($this->zoro, $invitation);
    $team = $accept->handle($this->zoro, $stale);

    expect($team->is($this->crew))->toBeTrue()
        ->and($this->crew->memberships()->where('user_id', $this->zoro->id)->count())->toBe(1);
});

test('the accept action re-checks expiry and email on the locked invitation', function (array $change) {
    $invitation = TeamInvitation::factory()->for($this->crew)->create(['email' => 'zoro@strawhat.pirates']);
    $stale = $invitation->fresh();
    $invitation->update($change);

    expect(fn () => resolve(AcceptTeamInvitation::class)->handle($this->zoro, $stale))
        ->toThrow(ValidationException::class);

    expect($this->zoro->belongsToTeam($this->crew))->toBeFalse();
})->with([
    'expired' => [['expires_at' => now()->subMinute()]],
    'sent to someone else' => [['email' => 'nami@strawhat.pirates']],
]);
