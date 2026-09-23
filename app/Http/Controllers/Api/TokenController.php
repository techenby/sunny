<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    private const int LIFETIME_DAYS = 30;

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

        $token = $this->issue($user, $request->device_name);

        return response()->json([
            ...$user->toArray(),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601String(),
        ]);
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

    /**
     * @param  array<int, string>  $abilities
     */
    private function issue(User $user, string $name, array $abilities = ['*']): NewAccessToken
    {
        return $user->createToken($name, $abilities, now()->addDays(self::LIFETIME_DAYS));
    }
}
