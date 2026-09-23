<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LockoutResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    private const int LIFETIME_DAYS = 30;

    private const int CHALLENGE_MINUTES = 5;

    public static function challengeKey(string $challenge): string
    {
        return 'api-two-factor-challenge:' . hash('sha256', $challenge);
    }

    public function store(Request $request, LoginRateLimiter $limiter): JsonResponse
    {
        $request->validate([
            Fortify::username() => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['required'],
        ]);

        if (config('fortify.lowercase_usernames')) {
            $request->merge([Fortify::username() => Str::lower($request->input(Fortify::username()))]);
        }

        if ($limiter->tooManyAttempts($request)) {
            event(new Lockout($request));

            return app(LockoutResponse::class)->toResponse($request);
        }

        $user = $this->authenticate($request, $limiter);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $challenge = Str::random(64);

            Cache::put(self::challengeKey($challenge), [
                'user_id' => $user->id,
                'device_name' => $request->device_name,
            ], now()->addMinutes(self::CHALLENGE_MINUTES));

            return response()->json([
                'two_factor' => true,
                'challenge' => $challenge,
            ]);
        }

        return $this->tokenResponse($user, $this->issue($user, $request->device_name));
    }

    public function twoFactor(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $response = Cache::lock(self::challengeKey($request->challenge) . ':lock', 10)
            ->get(fn (): JsonResponse => $this->completeChallenge($request, $provider));

        throw_if($response === false, ValidationException::withMessages([
            'challenge' => ['This two-factor challenge is already being verified.'],
        ]));

        return $response;
    }

    public function refresh(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        abort_unless($current instanceof PersonalAccessToken, 400, 'Only token-authenticated requests can be refreshed.');

        $expiresAt = $current->expires_at
            ? now()->addSeconds($current->created_at->diffInSeconds($current->expires_at))
            : null;

        $token = $request->user()->createToken($current->name, $current->abilities, $expiresAt);

        $current->delete();

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ]);
    }

    public function destroy(Request $request): Response
    {
        $current = $request->user()->currentAccessToken();

        abort_unless($current instanceof PersonalAccessToken, 400, 'Only token-authenticated requests can log out.');

        $current->delete();

        return response()->noContent();
    }

    private function authenticate(Request $request, LoginRateLimiter $limiter): User
    {
        $guard = config('fortify.guard');
        $provider = Auth::guard($guard)->getProvider();
        $credentials = $request->only(Fortify::username(), 'password');

        /** @var User|null $user */
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            event(new Failed($guard, $user, $credentials));

            $limiter->increment($request);

            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.failed')],
            ]);
        }

        if (config('hashing.rehash_on_login', true)) {
            $provider->rehashPasswordIfRequired($user, $credentials);
        }

        $limiter->clear($request);

        return $user;
    }

    private function completeChallenge(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $pending = Cache::get(self::challengeKey($request->challenge));
        $user = $pending ? User::find($pending['user_id']) : null;

        throw_unless($user?->hasEnabledTwoFactorAuthentication(), ValidationException::withMessages([
            'challenge' => ['The two-factor challenge has expired. Please sign in again.'],
        ]));

        $recoveryCode = $request->recovery_code
            ? collect($user->recoveryCodes())->first(fn (string $code): bool => hash_equals($code, $request->recovery_code))
            : null;

        if ($recoveryCode) {
            $user->replaceRecoveryCode($recoveryCode);
        } elseif (! $request->code || ! $provider->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $request->code)) {
            event(new TwoFactorAuthenticationFailed($user));

            throw ValidationException::withMessages([
                $request->recovery_code ? 'recovery_code' : 'code' => [__('The provided two factor authentication code was invalid.')],
            ]);
        }

        Cache::forget(self::challengeKey($request->challenge));

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        return $this->tokenResponse($user, $this->issue($user, $pending['device_name']));
    }

    private function tokenResponse(User $user, NewAccessToken $token): JsonResponse
    {
        return response()->json([
            ...$user->toArray(),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601String(),
        ]);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function issue(User $user, string $name, array $abilities = ['*']): NewAccessToken
    {
        return $user->createToken($name, $abilities, now()->addDays(self::LIFETIME_DAYS));
    }
}
