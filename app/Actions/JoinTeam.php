<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class JoinTeam
{
    public static function handle(User $user, TeamInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation) {
            abort_if($user->email !== $invitation->email, 403, __('This invitation belongs to a different email address.'));

            if (! $invitation->team->hasUser($user)) {
                $invitation->team->memberships()->create([
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                ]);
            }

            $user->switchTeam($invitation->team);

            $invitation->update(['accepted_at' => now()]);
        });
    }
}
