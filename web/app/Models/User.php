<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasTeams;
use App\Http\Integrations\Bento\Requests\CreateDataDeletionRequest;
use Bentonow\BentoLaravel\Facades\Bento;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use LogicException;

#[Fillable(['name', 'email', 'password', 'current_team_id', 'timezone', 'onboarded_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use HasApiTokens;
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasTeams;
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    public const int VERIFICATION_EMAILS_PER_DAY = 20;

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1) . Str::substr($initials, -1)
            : $initials;
    }

    /** @return Collection<int, Team> */
    public function teamsBlockingDeletion(): Collection
    {
        return $this->ownedTeams()
            ->whereHas('memberships', fn (Builder $query) => $query->where('user_id', '!=', $this->id))
            ->orderBy('teams.name')
            ->get();
    }

    public function deletionBlockedMessage(): ?string
    {
        $teams = $this->teamsBlockingDeletion();

        if ($teams->isEmpty()) {
            return null;
        }

        return trans_choice(
            'Other people still use :teams. Make someone else its owner, or remove its other members, before you delete your account.|Other people still use :teams. Make someone else the owner of each, or remove their other members, before you delete your account.',
            $teams->count(),
            ['teams' => $teams->pluck('name')->join(', ', __(' and '))],
        );
    }

    public function needsOnboarding(): bool
    {
        return $this->onboarded_at === null;
    }

    public function purge(): void
    {
        throw_if($this->teamsBlockingDeletion()->isNotEmpty(), LogicException::class, 'A user who owns a team other people use cannot be deleted.');

        DB::transaction(function (): void {
            $this->ownedTeams()->withTrashed()->get()->each->purge();

            Checklist::withTrashed()->where('user_id', $this->id)->forceDelete();
            Routine::withTrashed()->where('user_id', $this->id)->forceDelete();

            $this->teamMemberships()->delete();
            $this->signOutEverywhere();

            $this->delete();
        });

        if (app()->isProduction() && filled(config('bentonow.secret_key'))) {
            rescue(fn () => Bento::send(new CreateDataDeletionRequest($this->email)));
        }
    }

    public function signOutEverywhere(): void
    {
        $this->tokens()->delete();

        $oauthTokens = DB::table('oauth_access_tokens')->where('user_id', $this->id);
        DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $oauthTokens->clone()->select('id'))->delete();
        $oauthTokens->delete();
        DB::table('oauth_auth_codes')->where('user_id', $this->id)->delete();
        DB::table('oauth_device_codes')->where('user_id', $this->id)->delete();
        DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
