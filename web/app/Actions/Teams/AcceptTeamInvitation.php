<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Actions\Teams\Concerns\AnswersTeamInvitations;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AcceptTeamInvitation
{
    use AnswersTeamInvitations;

    public function handle(User $user, TeamInvitation $invitation): Team
    {
        return DB::transaction(function () use ($user, $invitation) {
            $locked = $this->lockInvitation($invitation);
            $team = Team::query()->findOrFail($invitation->team_id);

            if (($locked === null || $locked->isAccepted()) && $user->belongsToTeam($team)) {
                return $team;
            }

            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(TeamInvitation::class, [$invitation->code]);
            }

            $this->ensureInvitationIsPendingFor($locked, $user);

            $team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $locked->role],
            );

            $locked->update(['accepted_at' => now()]);

            return $team;
        });
    }
}
