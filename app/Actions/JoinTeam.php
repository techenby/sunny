<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class JoinTeam
{
    public static function handle(User $user, int $invitationId): void
    {
        DB::transaction(function () use ($user, $invitationId) {
            $invitation = TeamInvitation::with('team')->findOrFail($invitationId);

            abort_if($user->email !== $invitation->email, 403, __('This invitation belongs to a different email address.'));

            $invitation->team->users()->attach($user);
            $user->switchTeam($invitation->team);
            $invitation->delete();
        });
    }
}
