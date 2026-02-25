<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\JoinTeam;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AcceptTeamInvitation extends Controller
{
    public function __invoke(TeamInvitation $invitation): RedirectResponse
    {
        if (Auth::check()) {
            JoinTeam::handle(Auth::user(), $invitation->id);

            return to_route('dashboard');
        }

        session(['team_invitation_id' => $invitation->id]);

        $existingUser = User::query()->where('email', $invitation->email)->exists();

        return to_route($existingUser ? 'login' : 'register');
    }
}
