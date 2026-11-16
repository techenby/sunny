<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\TeamInvitation;
use App\Models\User;
use Bentonow\BentoLaravel\Facades\Bento;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateTeam $createTeam) {}

    /** @param  array<string, string>  $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'onboarded_at' => $this->hasPendingInvitation($input['email']) ? now() : null,
            ]);

            $this->createTeam->handle($user, $user->name . "'s Team", isPersonal: true);

            if (app()->isProduction()) {
                $name = str($user->name)->explode(' ', 2);
                Bento::track(
                    $user->email,
                    '$completed_onboarding',
                    fields: ['first_name' => $name->first(), 'last_name' => $name->get(1, '')],
                    details: ['source' => 'register']
                );
            }

            return $user;
        });
    }

    private function hasPendingInvitation(string $email): bool
    {
        return TeamInvitation::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->whereNull('accepted_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->exists();
    }
}
