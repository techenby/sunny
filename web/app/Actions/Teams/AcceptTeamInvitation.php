<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Actions\Teams\Concerns\AnswersTeamInvitations;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptTeamInvitation
{
    use AnswersTeamInvitations;

    public function handle(User $user, TeamInvitation $invitation): Team
    {
        $this->ensureInvitationIsPendingFor($invitation, $user);

        return DB::transaction(function () use ($user, $invitation) {
            $invitation->team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);

            return $invitation->team;
        });
    }
}
