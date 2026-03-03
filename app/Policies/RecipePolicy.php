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
        return $recipe->team_id === $user->current_team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $recipe->team_id === $user->current_team_id;
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $recipe->team_id === $user->current_team_id;
    }

    public function remix(User $user, Recipe $recipe): bool
    {
        return $recipe->team_id === $user->current_team_id;
    }
}
