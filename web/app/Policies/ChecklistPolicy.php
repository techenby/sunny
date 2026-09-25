<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Checklist;
use App\Models\User;

class ChecklistPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Checklist $checklist): bool
    {
        return $user->belongsToTeam($checklist->team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Checklist $checklist): bool
    {
        return $user->belongsToTeam($checklist->team);
    }

    public function delete(User $user, Checklist $checklist): bool
    {
        return $user->belongsToTeam($checklist->team);
    }

    public function restore(User $user, Checklist $checklist): bool
    {
        return $user->belongsToTeam($checklist->team);
    }

    public function forceDelete(User $user, Checklist $checklist): bool
    {
        return $user->belongsToTeam($checklist->team);
    }
}
