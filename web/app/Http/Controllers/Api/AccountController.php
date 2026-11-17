<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateUserProfileInformation $updateProfile): UserResource
    {
        $updateProfile->handle($request->user(), $request->validated());

        return UserResource::make($request->user());
    }

    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $request->user()->update(['password' => $request->validated('password')]);

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

    public function destroy(Request $request): Response
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = $request->user();

        if ($message = $user->deletionBlockedMessage()) {
            throw ValidationException::withMessages(['teams' => [$message]]);
        }

        $user->purge();

        return response()->noContent();
    }
}
