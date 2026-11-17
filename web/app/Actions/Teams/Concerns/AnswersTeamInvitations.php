<?php

declare(strict_types=1);

namespace App\Actions\Teams\Concerns;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait AnswersTeamInvitations
{
    protected function lockInvitation(TeamInvitation $invitation): ?TeamInvitation
    {
        return TeamInvitation::query()->whereKey($invitation->id)->lockForUpdate()->first();
    }

    protected function ensureInvitationIsPendingFor(TeamInvitation $invitation, User $user): void
    {
        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation has expired.')],
            ]);
        }

        if (Str::lower($invitation->email) !== Str::lower($user->email)) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation was sent to a different email address.')],
            ]);
        }
    }
}
