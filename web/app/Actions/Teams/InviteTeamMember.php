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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteTeamMember
{
    public const int PER_HOUR = 10;

    public const int PER_DAY = 30;

    public function handle(Team $team, User $inviter, string $email, TeamRole $role): TeamInvitation
    {
        $this->ensureInviterIsWithinLimits($inviter);

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

        RateLimiter::hit($this->hourKey($inviter), 3600);
        RateLimiter::hit($this->dayKey($inviter), 86400);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        return $invitation;
    }

    private function ensureInviterIsWithinLimits(User $inviter): void
    {
        $message = match (true) {
            RateLimiter::tooManyAttempts($this->dayKey($inviter), self::PER_DAY) => __('You’ve sent a lot of invitations today. Try again tomorrow.'),
            RateLimiter::tooManyAttempts($this->hourKey($inviter), self::PER_HOUR) => __('You’ve sent a lot of invitations. Try again in an hour.'),
            default => null,
        };

        throw_if($message !== null, ValidationException::withMessages(['email' => [$message]]));
    }

    private function hourKey(User $inviter): string
    {
        return "invitations:hour:{$inviter->id}";
    }

    private function dayKey(User $inviter): string
    {
        return "invitations:day:{$inviter->id}";
    }
}
