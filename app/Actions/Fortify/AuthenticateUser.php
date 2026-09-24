<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;

class AuthenticateUser
{
    public function handle(Request $request): ?User
    {
        $guard = Auth::guard(config('fortify.guard'));
        $credentials = $request->only(Fortify::username(), 'password');

        if (! $guard->validate($credentials)) {
            return null;
        }

        /** @var User $user */
        $user = $guard->getLastAttempted();

        if (config('hashing.rehash_on_login', true)) {
            $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);
        }

        return $user;
    }
}
