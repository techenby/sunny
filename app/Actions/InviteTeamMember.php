<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;

class InviteTeamMember
{
    public static function handle(Team $team, string $email): TeamInvitation
    {
        $invitation = $team->invitations()->create(['email' => $email]);

        Notification::route('mail', $email)
            ->notify(new TeamInvitationNotification($invitation));

        return $invitation;
    }
}
