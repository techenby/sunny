<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiEmailIsVerified
{
    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return $next($request);
        }

        return response()->json([
            'message' => __('Verify your email address to keep using SunnyHome.'),
            'email_verification_required' => true,
        ], Response::HTTP_LOCKED);
    }
}
