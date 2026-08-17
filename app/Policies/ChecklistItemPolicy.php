<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ChecklistItem;
use App\Models\User;

class ChecklistItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChecklistItem $item): bool
    {
        return $item->checklist->team_id === $user->current_team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ChecklistItem $item): bool
    {
        return $item->checklist->team_id === $user->current_team_id;
    }

    public function delete(User $user, ChecklistItem $item): bool
    {
        return $item->checklist->team_id === $user->current_team_id;
    }

    public function complete(User $user, ChecklistItem $item): bool
    {
        return $item->checklist->team_id === $user->current_team_id;
    }
}
