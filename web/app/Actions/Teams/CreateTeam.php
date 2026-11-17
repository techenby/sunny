<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    public function handle(User $user, string $name, bool $isPersonal = false, bool $switchTeam = true): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal, $switchTeam) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            if ($switchTeam) {
                $user->switchTeam($team);
            }

            return $team;
        });
    }
}
