<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recipe $recipe): bool
    {
        return $user->belongsToTeam($recipe->team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->belongsToTeam($recipe->team);
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->belongsToTeam($recipe->team);
    }

    public function copy(User $user, Recipe $recipe): bool
    {
        return $user->belongsToTeam($recipe->team);
    }

    public function remix(User $user, Recipe $recipe): bool
    {
        return $user->belongsToTeam($recipe->team);
    }

    public function share(User $user, Recipe $recipe): bool
    {
        return $user->belongsToTeam($recipe->team);
    }
}
