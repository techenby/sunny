<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
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
