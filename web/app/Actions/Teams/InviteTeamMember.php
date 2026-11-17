<?php

declare(strict_types=1);

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteTeamMember
{
    public function handle(Team $team, User $inviter, string $email, TeamRole $role): TeamInvitation
    {
        $invitation = DB::transaction(function () use ($team, $inviter, $email, $role) {
            $team->invitations()
                ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
                ->delete();

            return $team->invitations()->create([
                'email' => $email,
                'role' => $role,
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(3),
            ]);
        });

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        return $invitation;
    }
}
