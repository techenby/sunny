<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\LeaveTeam;
use App\Actions\Teams\UpdateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTeamRequest;
use App\Http\Requests\Api\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function store(StoreTeamRequest $request, CreateTeam $createTeam): JsonResponse
    {
        $team = $createTeam->handle($request->user(), $request->validated('name'), switchTeam: false);

        return TeamResource::withMembers($team->refresh())
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTeamRequest $request, Team $team, UpdateTeam $updateTeam): TeamResource
    {
        $attributes = $request->validated();

        if (isset($attributes['address'])) {
            $attributes['address'] = Arr::map($attributes['address'], fn (mixed $value): string => (string) $value);
        }

        return TeamResource::withMembers($updateTeam->handle($team, $attributes)->refresh());
    }

    public function leave(Request $request, Team $team, LeaveTeam $leaveTeam): Response
    {
        Gate::authorize('leave', $team);

        $leaveTeam->handle($request->user(), $team);

        return response()->noContent();
    }
}
