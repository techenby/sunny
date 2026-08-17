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
        return $checklist->team_id === $user->current_team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Checklist $checklist): bool
    {
        return $checklist->team_id === $user->current_team_id;
    }

    public function delete(User $user, Checklist $checklist): bool
    {
        return $checklist->team_id === $user->current_team_id;
    }

    public function restore(User $user, Checklist $checklist): bool
    {
        return $checklist->team_id === $user->current_team_id;
    }

    public function forceDelete(User $user, Checklist $checklist): bool
    {
        return $checklist->team_id === $user->current_team_id;
    }
}
