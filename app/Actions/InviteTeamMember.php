<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;

class InviteTeamMember
{
    public static function handle(Team $team, string $email, TeamRole $role = TeamRole::Member, ?User $invitedBy = null): TeamInvitation
    {
        $invitation = $team->invitations()->create([
            'email' => $email,
            'role' => $role,
            'invited_by' => $invitedBy?->id,
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $email)
            ->notify(new TeamInvitationNotification($invitation));

        return $invitation;
    }
}
