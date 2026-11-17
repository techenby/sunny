<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;

class UpdateUserProfileInformation
{
    /** @param  array{name?: string, email?: string}  $input */
    public function handle(User $user, array $input): void
    {
        $user->fill($input);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
    }
}
