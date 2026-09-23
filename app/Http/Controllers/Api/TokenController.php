<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    private const int LIFETIME_DAYS = 30;

    private const int CHALLENGE_MINUTES = 5;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['required'],
        ]);

        $user = User::firstWhere('email', $request->email);

        throw_if(! $user || ! Hash::check($request->password, $user->password), ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]));

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $challenge = Str::random(64);

            Cache::put($this->challengeKey($challenge), [
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

        $pending = Cache::get($this->challengeKey($request->challenge));
        $user = $pending ? User::find($pending['user_id']) : null;

        throw_unless($user, ValidationException::withMessages([
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

        Cache::forget($this->challengeKey($request->challenge));

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        return $this->tokenResponse($user, $this->issue($user, $pending['device_name']));
    }

    public function refresh(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        abort_unless($current instanceof PersonalAccessToken, 400, 'Only token-authenticated requests can be refreshed.');

        $token = $this->issue($request->user(), $current->name, $current->abilities);

        $current->delete();

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601String(),
        ]);
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

    private function challengeKey(string $challenge): string
    {
        return 'api-two-factor-challenge:' . hash('sha256', $challenge);
    }
}
