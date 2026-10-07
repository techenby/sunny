<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        $this->configurePassport();
    }

    protected function configurePassport(): void
    {
        Passport::authorizationView(fn (array $parameters) => view('pages::auth.oauth-authorize', $parameters));

        Passport::tokensExpireIn(CarbonInterval::day());
        Passport::refreshTokensExpireIn(CarbonInterval::days(90));
    }

    protected function configureGates(): void
    {
        Gate::define('admin', function (User $user): bool {
            return $user->email === 'andy@techenby.com';
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
