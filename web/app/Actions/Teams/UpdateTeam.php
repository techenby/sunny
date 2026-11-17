<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateTeam
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(Team $team, array $attributes): Team
    {
        return DB::transaction(function () use ($team, $attributes) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update($attributes);

            return $team;
        });
    }
}
