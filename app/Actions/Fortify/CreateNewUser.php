<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Bentonow\BentoLaravel\Facades\Bento;
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

        if (app()->isProduction()) {
            [$firstName, $lastName] = str($user->name)->explode(' ', 2);
            Bento::track(
                $user->email,
                '$completed_onboarding',
                fields: ['first_name' => $firstName, 'last_name' => $lastName],
                details: ['source' => 'register']
            );
        }

        return $user;
    }
}
