<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\TeamInvitation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class InvitationEmailMatch implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! session()->has('team_invitation_id')) {
            return;
        }

        $invitation = TeamInvitation::find(session()->get('team_invitation_id'));

        if (! $invitation) {
            session()->forget('team_invitation_id');

            return;
        }

        if ($invitation->email !== $value) {
            $fail("The {$attribute} must match the {$attribute} on the invitation.");
        }
    }
}
