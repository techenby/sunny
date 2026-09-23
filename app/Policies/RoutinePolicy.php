<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Routine;
use App\Models\User;

class RoutinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Routine $routine): bool
    {
        return $user->belongsToTeam($routine->team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Routine $routine): bool
    {
        return $user->belongsToTeam($routine->team);
    }

    public function delete(User $user, Routine $routine): bool
    {
        return $user->belongsToTeam($routine->team);
    }

    public function restore(User $user, Routine $routine): bool
    {
        return $user->belongsToTeam($routine->team);
    }

    public function forceDelete(User $user, Routine $routine): bool
    {
        return $user->belongsToTeam($routine->team);
    }

    /**
     * Anyone on the team can tick a step off, including household routines
     * nobody has claimed.
     */
    public function complete(User $user, Routine $routine): bool
    {
        return $user->belongsToTeam($routine->team);
    }
}
