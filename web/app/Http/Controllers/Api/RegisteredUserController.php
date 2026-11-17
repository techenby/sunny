<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Api\Concerns\IssuesTokens;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class RegisteredUserController extends Controller
{
    use IssuesTokens;

    public function __invoke(Request $request, CreateNewUser $createNewUser): JsonResponse
    {
        abort_unless(Features::enabled(Features::registration()), 404);

        $request->validate([
            'device_name' => ['required'],
        ]);

        if (config('fortify.lowercase_usernames') && is_string($request->input(Fortify::username()))) {
            $request->merge([Fortify::username() => Str::lower($request->input(Fortify::username()))]);
        }

        event(new Registered($user = $createNewUser->create($request->all())));

        return $this->tokenResponse($user, $this->issue($user, $request->device_name), 201);
    }
}
