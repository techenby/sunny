<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\AuthenticateUser;
use App\Enums\TokenLifetime;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\TwoFactorChallenge;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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

            return resolve(LockoutResponse::class)->toResponse($request);
        }

        $user = $this->authenticate($request, $limiter);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'two_factor' => true,
                'challenge' => TwoFactorChallenge::issue($user, $request->device_name),
            ]);
        }

        return $this->tokenResponse($user, $this->issue($user, $request->device_name));
    }

    public function twoFactor(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'prohibits:code'],
        ]);

        $response = TwoFactorChallenge::lock($request->challenge)
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

        $token = $request->user()->createToken($current->name, $current->abilities, TokenLifetime::fromToken($current)->expiresAt());

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
        $user = resolve(AuthenticateUser::class)->handle($request);

        if (! $user) {
            $guardName = config('fortify.guard');

            event(new Failed($guardName, Auth::guard($guardName)->getLastAttempted(), $request->only(Fortify::username(), 'password')));

            $limiter->increment($request);

            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.failed')],
            ]);
        }

        $limiter->clear($request);

        return $user;
    }

    private function completeChallenge(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $pending = TwoFactorChallenge::find($request->challenge);
        $user = $pending?->user();

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

        TwoFactorChallenge::forget($request->challenge);

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        return $this->tokenResponse($user, $this->issue($user, $pending->deviceName));
    }

    private function tokenResponse(User $user, NewAccessToken $token): JsonResponse
    {
        return response()->json([
            ...UserResource::make($user)->resolve(),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601String(),
        ]);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function issue(User $user, string $name, array $abilities = ['*']): NewAccessToken
    {
        return $user->createToken($name, $abilities, TokenLifetime::ThirtyDays->expiresAt());
    }
}
