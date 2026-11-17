<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;

class UpdateTeamMemberRole
{
    public function handle(Team $team, int $userId, TeamRole $role): void
    {
        $team->memberships()
            ->where('user_id', $userId)
            ->where('role', '!=', TeamRole::Owner)
            ->firstOrFail()
            ->update(['role' => $role]);
    }
}
