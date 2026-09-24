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
        return $user->belongsToTeam($item->checklist->team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ChecklistItem $item): bool
    {
        return $user->belongsToTeam($item->checklist->team);
    }

    public function delete(User $user, ChecklistItem $item): bool
    {
        return $user->belongsToTeam($item->checklist->team);
    }

    public function complete(User $user, ChecklistItem $item): bool
    {
        return $user->belongsToTeam($item->checklist->team);
    }
}
