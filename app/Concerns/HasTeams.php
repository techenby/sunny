<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTeams
{
    /** @return HasMany<Team, $this> */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'user_id');
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /** @return BelongsTo<Team, $this> */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function ownsTeam(Team $team): bool
    {
        return $this->id === $team->user_id;
    }

    public function belongsToTeam(Team $team): bool
    {
        return $this->ownsTeam($team) || $this->teams->contains($team);
    }

    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    public function addTeam(string $name): Team
    {
        $team = $this->ownedTeams()->create([
            'name' => $name,
        ]);

        $this->teams()->attach($team);

        $this->switchTeam($team);

        return $team;
    }

    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->forceFill(['current_team_id' => $team->id])->save();

        $this->setRelation('currentTeam', $team);

        return true;
    }
}
