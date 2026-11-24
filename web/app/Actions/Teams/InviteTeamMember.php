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
use Throwable;

class InviteTeamMember
{
    public const int PER_HOUR = 10;

    public const int PER_DAY = 30;

    public function handle(Team $team, User $inviter, string $email, TeamRole $role): TeamInvitation
    {
        $this->reserveInvitation($inviter);

        try {
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
        } catch (Throwable $exception) {
            RateLimiter::decrement($this->hourKey($inviter), 3600);
            RateLimiter::decrement($this->dayKey($inviter), 86400);

            throw $exception;
        }

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        return $invitation;
    }

    private function reserveInvitation(User $inviter): void
    {
        throw_if(RateLimiter::hit($this->hourKey($inviter), 3600) > self::PER_HOUR, ValidationException::withMessages([
            'email' => [__('You’ve sent a lot of invitations. Try again in an hour.')],
        ]));

        throw_if(RateLimiter::hit($this->dayKey($inviter), 86400) > self::PER_DAY, ValidationException::withMessages([
            'email' => [__('You’ve sent a lot of invitations today. Try again tomorrow.')],
        ]));
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
