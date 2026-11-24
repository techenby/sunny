<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTeam
{
    public function handle(User $user, string $name, bool $isPersonal = false, bool $switchTeam = true): Team
    {
        throw_if(! $isPersonal && $user->ownedTeams()->count() >= Team::MAX_OWNED_PER_USER, ValidationException::withMessages([
            'name' => [__('You can own up to :count teams.', ['count' => Team::MAX_OWNED_PER_USER])],
        ]));

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
