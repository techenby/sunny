<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Teams\RemoveTeamMember;
use App\Actions\Teams\UpdateTeamMemberRole;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTeamMemberRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TeamMemberController extends Controller
{
    public function update(UpdateTeamMemberRequest $request, Team $team, User $member, UpdateTeamMemberRole $updateTeamMemberRole): TeamResource
    {
        $updateTeamMemberRole->handle($team, $member->id, TeamRole::from($request->validated('role')));

        return TeamResource::withMembers($team);
    }

    public function destroy(Team $team, User $member, RemoveTeamMember $removeTeamMember): Response
    {
        Gate::authorize('removeMember', $team);

        $removeTeamMember->handle($team, $member);

        return response()->noContent();
    }
}
