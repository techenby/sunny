<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoutineTask;
use App\Models\User;

class RoutineTaskPolicy
{
    public function complete(User $user, RoutineTask $routineTask): bool
    {
        return $routineTask->routine->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RoutineTask $routineTask): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RoutineTask $routineTask): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RoutineTask $routineTask): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RoutineTask $routineTask): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RoutineTask $routineTask): bool
    {
        return false;
    }
}
