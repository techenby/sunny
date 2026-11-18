<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;

class PasswordResetLinkController extends Controller
{
    public function __invoke(SendPasswordResetLinkRequest $request): JsonResponse
    {
        abort_unless(Features::enabled(Features::resetPasswords()), 404);

        if (config('fortify.lowercase_usernames')) {
            $request->merge([Fortify::email() => Str::lower($request->input(Fortify::email()))]);
        }

        Password::broker(config('fortify.passwords'))->sendResetLink($request->only(Fortify::email()));

        return response()->json([
            'message' => __('If that email has an account, we’ve sent it a link to reset the password.'),
        ], 202);
    }
}
