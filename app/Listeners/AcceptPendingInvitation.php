<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\JoinTeam;
use Illuminate\Auth\Events\Login;
use Throwable;

class AcceptPendingInvitation
{
    public function handle(Login $event): void
    {
        if (! session()->has('team_invitation_id')) {
            return;
        }

        try {
            JoinTeam::handle($event->user, session()->pull('team_invitation_id'));
        } catch (Throwable) {
            // Invitation may have been deleted or belong to a different email.
            // Fail silently so the login is not interrupted.
        }
    }
}
