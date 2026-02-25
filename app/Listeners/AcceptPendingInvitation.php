<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\JoinTeam;
use Illuminate\Auth\Events\Login;

class AcceptPendingInvitation
{
    public function handle(Login $event): void
    {
        if (! session()->has('team_invitation_id')) {
            return;
        }

        JoinTeam::handle($event->user, session()->pull('team_invitation_id'));
    }
}
