<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Actions\Teams\DeclineTeamInvitation;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamInvitationResource;
use App\Http\Resources\TeamResource;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvitationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        if (! $request->user()->hasVerifiedEmail()) {
            return TeamInvitationResource::collection([]);
        }

        $invitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->pendingFor($request->user())
            ->latest()
            ->get();

        return TeamInvitationResource::collection($invitations);
    }

    public function accept(Request $request, TeamInvitation $invitation, AcceptTeamInvitation $acceptInvitation): TeamResource
    {
        return TeamResource::withMembers($acceptInvitation->handle($request->user(), $invitation));
    }

    public function decline(Request $request, TeamInvitation $invitation, DeclineTeamInvitation $declineInvitation): Response
    {
        $declineInvitation->handle($request->user(), $invitation);

        return response()->noContent();
    }
}
