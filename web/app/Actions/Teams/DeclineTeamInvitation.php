<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Actions\Teams\Concerns\AnswersTeamInvitations;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeclineTeamInvitation
{
    use AnswersTeamInvitations;

    public function handle(User $user, TeamInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation): void {
            $locked = $this->lockInvitation($invitation);

            if ($locked === null) {
                return;
            }

            $this->ensureInvitationIsPendingFor($locked, $user);

            $locked->delete();
        });
    }
}
