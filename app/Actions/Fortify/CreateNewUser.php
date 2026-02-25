<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\JoinTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /** @param  array<string, string>  $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $user->addTeam($user->name . "'s Team");

        $this->acceptPendingInvitation($user);

        return $user;
    }

    private function acceptPendingInvitation(User $user): void
    {
        if (! session()->has('team_invitation_id')) {
            return;
        }

        JoinTeam::handle($user, session()->pull('team_invitation_id'));
    }
}
