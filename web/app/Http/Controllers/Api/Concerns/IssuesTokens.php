<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\TokenLifetime;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\NewAccessToken;

trait IssuesTokens
{
    private function tokenResponse(User $user, NewAccessToken $token, int $status = 200): JsonResponse
    {
        return response()->json([
            ...UserResource::make($user)->resolve(),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601String(),
        ], $status);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function issue(User $user, string $name, array $abilities = ['*']): NewAccessToken
    {
        return $user->createToken($name, $abilities, TokenLifetime::ThirtyDays->expiresAt());
    }
}
