<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

class RemoveTeamMember
{
    public function handle(Team $team, User $user): void
    {
        $team->memberships()
            ->where('user_id', $user->id)
            ->where('role', '!=', TeamRole::Owner)
            ->firstOrFail()
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }
    }
}
