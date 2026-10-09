<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\KioskTeam;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetTeamUrlDefaults
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $currentTeam = KioskTeam::inKioskSession()
            ? KioskTeam::device()?->team
            : $request->user()?->currentTeam;

        if ($currentTeam) {
            URL::defaults([
                'current_team' => $currentTeam->slug,
                'team' => $currentTeam->slug,
            ]);
        }

        return $next($request);
    }
}
