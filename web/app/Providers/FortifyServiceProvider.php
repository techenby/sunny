<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasskeyLoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\TwoFactorChallenge;
use App\Support\VerificationEmailQuota;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Head\Facades\Head;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(PasskeyLoginResponseContract::class, PasskeyLoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::authenticateUsing(fn (Request $request): ?User => resolve(AuthenticateUser::class)->handle($request));
    }

    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            Head::title('Log in');

            return view('pages::auth.login', [
                'teamInvitation' => $this->teamInvitation($request),
            ]);
        });
        Fortify::verifyEmailView(function () {
            Head::title('Verify email');

            return view('pages::auth.verify-email');
        });
        Fortify::twoFactorChallengeView(function () {
            Head::title('Two-factor authentication');

            return view('pages::auth.two-factor-challenge');
        });
        Fortify::confirmPasswordView(function () {
            Head::title('Confirm password');

            return view('pages::auth.confirm-password');
        });
        Fortify::registerView(function (Request $request) {
            Head::title('Register');

            return view('pages::auth.register', [
                'teamInvitation' => $this->teamInvitation($request),
            ]);
        });
        Fortify::resetPasswordView(function () {
            Head::title('Reset password');

            return view('pages::auth.reset-password');
        });
        Fortify::requestPasswordResetLinkView(function () {
            Head::title('Forgot password');

            return view('pages::auth.forgot-password');
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('api-two-factor', function (Request $request) {
            $userId = TwoFactorChallenge::find((string) $request->input('challenge'))?->userId;

            return Limit::perMinute(5)->by($userId ? "user:{$userId}" : $request->ip());
        });

        RateLimiter::for('verification-email', function (Request $request) {
            $user = $request->user();

            if ($request->isMethod('PATCH') && (! $request->has('email') || $request->input('email') === $user->email)) {
                return Limit::none();
            }

            if ($request->isMethod('GET')) {
                return Limit::perMinute(6)->by("verify:{$user->id}");
            }

            $quota = new VerificationEmailQuota($user, 'request');

            throw_unless($quota->claim(), ThrottleRequestsException::class, 'Too Many Attempts.', null, ['Retry-After' => $quota->availableIn()]);

            return Limit::none();
        });

        RateLimiter::for('account-forms', function (Request $request) {
            return Limit::perMinute(6)
                ->by($request->route()->getName() . '|' . $request->ip())
                ->response(fn (Request $request, array $headers) => throw ValidationException::withMessages([
                    'email' => [__('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $headers['Retry-After']])],
                ])->status(Response::HTTP_TOO_MANY_REQUESTS));
        });

        RateLimiter::for('api', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(240)->by($userId ? "user:{$userId}" : $request->ip());
        });

        RateLimiter::for('recipe-imports', fn (Request $request) => Limit::perMinute(30)->by("user:{$request->user()?->id}"));

        RateLimiter::for('mcp-oauth', function (Request $request) {
            if (! $request->routeIs('mcp.oauth.register')) {
                return Limit::perMinute(30)->by($request->ip());
            }

            return [
                Limit::perMinute(5)->by("register:{$request->ip()}"),
                Limit::perDay(30)->by("register:{$request->ip()}:day"),
            ];
        });

        RateLimiter::for('kiosk-pairing', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()) . '|' . $request->ip(),
            );
        });
    }

    /** @return array{code: string, teamName: string}|null */
    private function teamInvitation(Request $request): ?array
    {
        $invitationCode = $request->query('invitation');

        if (! is_string($invitationCode)) {
            return null;
        }

        $invitation = TeamInvitation::query()
            ->with('team')
            ->where('code', $invitationCode)
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->first();

        if (! $invitation) {
            return null;
        }

        return [
            'code' => $invitation->code,
            'teamName' => $invitation->team->name,
        ];
    }
}
