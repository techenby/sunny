<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;

class LeaveTeam
{
    public function handle(User $user, Team $team): void
    {
        $team->memberships()->where('user_id', $user->id)->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }
    }
}
