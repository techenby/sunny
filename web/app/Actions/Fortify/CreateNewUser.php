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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public const int ACCOUNTS_PER_NETWORK_PER_DAY = 10;

    public function __construct(private CreateTeam $createTeam) {}

    /** @param  array<string, string>  $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $limitKey = 'sign-up:' . request()->ip();

        throw_if(RateLimiter::tooManyAttempts($limitKey, self::ACCOUNTS_PER_NETWORK_PER_DAY), ValidationException::withMessages([
            'email' => [__('Too many accounts have been created from this network today. Please try again tomorrow.')],
        ]));

        $user = $this->createUser($input);

        RateLimiter::hit($limitKey, 86400);

        return $user;
    }

    /** @param  array<string, string>  $input */
    private function createUser(array $input): User
    {
        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'onboarded_at' => $this->hasPendingInvitation($input['email']) ? now() : null,
            ]);

            $this->createTeam->handle($user, $user->name . "'s Team", isPersonal: true);

            if (app()->isProduction() && filled(config('bentonow.secret_key'))) {
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
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->exists();
    }
}
