<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CalendarFeed;
use App\Models\User;

class CalendarFeedPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalendarFeed $feed): bool
    {
        return $feed->team_id === $user->current_team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CalendarFeed $feed): bool
    {
        return $feed->team_id === $user->current_team_id;
    }

    public function delete(User $user, CalendarFeed $feed): bool
    {
        return $feed->team_id === $user->current_team_id;
    }
}
