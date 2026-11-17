<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Teams\InviteTeamMember;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTeamInvitationRequest;
use App\Http\Resources\TeamInvitationResource;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TeamInvitationController extends Controller
{
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('view', $team);

        return TeamInvitationResource::collection($team->invitations()->latest()->get());
    }

    public function store(StoreTeamInvitationRequest $request, Team $team, InviteTeamMember $inviteTeamMember): JsonResponse
    {
        $invitation = $inviteTeamMember->handle($team, $request->user(), $request->validated('email'), TeamRole::from($request->validated('role')));

        return TeamInvitationResource::make($invitation)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Team $team, TeamInvitation $invitation): Response
    {
        Gate::authorize('cancelInvitation', $team);

        $invitation->delete();

        return response()->noContent();
    }
}
