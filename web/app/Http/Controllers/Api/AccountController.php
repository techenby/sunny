<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateUserProfileInformation $updateProfile): UserResource
    {
        $updateProfile->handle($request->user(), $request->validated());

        return UserResource::make($request->user());
    }

    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();

        $user->update(['password' => $request->validated('password')]);
        $user->signOutEverywhere(exceptToken: $user->currentAccessToken());

        return response()->noContent();
    }

    public function sendVerificationNotification(Request $request): Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->noContent();
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->noContent(202);
    }

    public function destroy(Request $request): Response|JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = $request->user();

        if ($message = $user->deletionBlockedMessage()) {
            return response()->json([
                'message' => $message,
                'errors' => ['teams' => [$message]],
                'blocking_teams' => $user->teamsBlockingDeletion()->map(fn (Team $team): array => [
                    'name' => $team->name,
                    'slug' => $team->slug,
                ]),
            ], 422);
        }

        $user->purge();

        return response()->noContent();
    }
}
