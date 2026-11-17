<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Actions\Teams\Concerns\AnswersTeamInvitations;
use App\Models\TeamInvitation;
use App\Models\User;

class DeclineTeamInvitation
{
    use AnswersTeamInvitations;

    public function handle(User $user, TeamInvitation $invitation): void
    {
        $this->ensureInvitationIsPendingFor($invitation, $user);

        $invitation->delete();
    }
}
