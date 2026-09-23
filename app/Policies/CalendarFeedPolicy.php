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
        return $user->belongsToTeam($feed->team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CalendarFeed $feed): bool
    {
        return $user->belongsToTeam($feed->team);
    }

    public function delete(User $user, CalendarFeed $feed): bool
    {
        return $user->belongsToTeam($feed->team);
    }
}
