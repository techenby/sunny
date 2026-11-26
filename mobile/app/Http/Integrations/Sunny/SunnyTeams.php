<?php

namespace App\Http\Integrations\Sunny;

use App\Enums\TeamRole;
use App\Http\Integrations\Sunny\Requests\AcceptInvitationRequest;
use App\Http\Integrations\Sunny\Requests\CancelTeamInvitationRequest;
use App\Http\Integrations\Sunny\Requests\CreateTeamInvitationRequest;
use App\Http\Integrations\Sunny\Requests\CreateTeamRequest;
use App\Http\Integrations\Sunny\Requests\DeclineInvitationRequest;
use App\Http\Integrations\Sunny\Requests\GetInvitationsRequest;
use App\Http\Integrations\Sunny\Requests\GetTeamInvitationsRequest;
use App\Http\Integrations\Sunny\Requests\LeaveTeamRequest;
use App\Http\Integrations\Sunny\Requests\RemoveTeamMemberRequest;
use App\Http\Integrations\Sunny\Requests\UpdateTeamMemberRequest;
use App\Http\Integrations\Sunny\Requests\UpdateTeamRequest;
use App\Models\Invitation;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class SunnyTeams
{
    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
        private readonly SunnyTeam $teams,
    ) {}

    public function create(string $name): Team
    {
        $team = $this->saveTeam($this->auth->authenticatedConnector()->send(new CreateTeamRequest($name))->json('data'));
        $this->teams->select($team->id);
        app(SunnySyncCoordinator::class)->dispatch();

        return $team;
    }

    /** @param  array{name?: string, timezone?: string, week_start?: int}  $attributes */
    public function update(Team $team, array $attributes): Team
    {
        return $this->saveTeam($this->auth->authenticatedConnector()->send(new UpdateTeamRequest($team->slug, $attributes))->json('data'));
    }

    public function leave(Team $team): void
    {
        $this->auth->authenticatedConnector()->send(new LeaveTeamRequest($team->slug));
        $this->store->forgetTeam($team);
        app(SunnySyncCoordinator::class)->dispatch();
    }

    public function changeRole(Team $team, int $memberId, TeamRole $role): Team
    {
        return $this->saveTeam($this->auth->authenticatedConnector()->send(new UpdateTeamMemberRequest($team->slug, $memberId, $role->value))->json('data'));
    }

    public function removeMember(Team $team, int $memberId): void
    {
        $this->auth->authenticatedConnector()->send(new RemoveTeamMemberRequest($team->slug, $memberId));

        $team->update(['members' => collect($team->members ?? [])->reject(fn (mixed $member): bool => ($member['id'] ?? null) === $memberId)->values()->all()]);
    }

    /** @return list<array<string, mixed>> */
    public function refreshTeamInvitations(Team $team, #[\SensitiveParameter] ?string $token = null): array
    {
        $invitations = $this->auth->authenticatedConnector($token)->send(new GetTeamInvitationsRequest($team->slug))->json('data');

        throw_unless(is_array($invitations) && array_is_list($invitations), UnexpectedValueException::class, 'Sunny did not return the team’s invitations.');

        $team->update(['invitations' => $invitations, 'invitations_fetched_at' => now()]);

        return $invitations;
    }

    public function invite(Team $team, string $email, TeamRole $role): void
    {
        $invitation = $this->auth->authenticatedConnector()->send(new CreateTeamInvitationRequest($team->slug, $email, $role->value))->json('data');

        if (is_array($invitation)) {
            $team->update(['invitations' => [$invitation, ...collect($team->invitations ?? [])->reject(fn (mixed $existing): bool => ($existing['code'] ?? null) === ($invitation['code'] ?? null))->values()->all()]]);
        }
    }

    public function cancelInvitation(Team $team, string $code): void
    {
        $this->auth->authenticatedConnector()->send(new CancelTeamInvitationRequest($team->slug, $code));
        $this->forgetTeamInvitation($team, $code);
    }

    public function forgetTeamInvitation(Team $team, string $code): void
    {
        $team->update(['invitations' => collect($team->invitations ?? [])->reject(fn (mixed $invitation): bool => ($invitation['code'] ?? null) === $code)->values()->all()]);
    }

    public function refreshInvitations(#[\SensitiveParameter] ?string $token = null): void
    {
        $invitations = $this->auth->authenticatedConnector($token)->send(new GetInvitationsRequest)->json('data');

        throw_unless(is_array($invitations) && array_is_list($invitations), UnexpectedValueException::class, 'Sunny did not return your invitations.');

        DB::transaction(function () use ($invitations): void {
            Invitation::query()->where('server', SunnyStore::server())->delete();

            foreach ($invitations as $invitation) {
                if (! is_string($invitation['code'] ?? null) || ! is_string($invitation['team']['name'] ?? null)) {
                    continue;
                }

                Invitation::query()->create([
                    'code' => $invitation['code'],
                    'server' => SunnyStore::server(),
                    'team_name' => $invitation['team']['name'],
                    'inviter_name' => $invitation['inviter']['name'] ?? null,
                    'role' => TeamRole::tryFrom($invitation['role'] ?? '') ?? TeamRole::Member,
                    'expires_at' => $invitation['expires_at'] ?? null,
                    'created_at' => $invitation['created_at'] ?? null,
                ]);
            }
        });
    }

    public function accept(Invitation $invitation): Team
    {
        $team = $this->saveTeam($this->auth->authenticatedConnector()->send(new AcceptInvitationRequest($invitation->code))->json('data'));
        $invitation->delete();
        $this->teams->select($team->id);
        app(SunnySyncCoordinator::class)->dispatch();

        return $team;
    }

    public function decline(Invitation $invitation): void
    {
        $this->auth->authenticatedConnector()->send(new DeclineInvitationRequest($invitation->code));
        $invitation->delete();
    }

    private function saveTeam(mixed $team): Team
    {
        throw_unless(is_array($team) && is_int($team['id'] ?? null) && is_string($team['name'] ?? null), UnexpectedValueException::class, 'Sunny did not return the team.');

        return $this->store->saveTeam($team);
    }
}
