<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedOrInGracePeriod
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasVerifiedEmail() || $user->isWithinEmailVerificationGracePeriod()) {
            return $next($request);
        }

        return response()->json([
            'message' => __('Verify your email address to keep using SunnyHome.'),
            'email_verification_required' => true,
        ], Response::HTTP_LOCKED);
    }
}
