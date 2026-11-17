<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Kiosk\CreateFeed;
use App\Actions\Kiosk\UpdateFeed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCalendarFeedRequest;
use App\Http\Requests\Api\UpdateCalendarFeedRequest;
use App\Http\Resources\CalendarFeedResource;
use App\Models\CalendarFeed;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CalendarFeedController extends Controller
{
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CalendarFeed::class);

        return CalendarFeedResource::collection($team->calendarFeeds()->orderBy('name')->get());
    }

    public function store(StoreCalendarFeedRequest $request, Team $team, CreateFeed $createFeed): JsonResponse
    {
        $existing = $request->filled('client_uuid')
            ? $team->calendarFeeds()->firstWhere('client_uuid', $request->validated('client_uuid'))
            : null;

        return CalendarFeedResource::make($existing ?? $createFeed->handle($team, $request->validated()))
            ->response()
            ->setStatusCode($existing ? 200 : 201);
    }

    public function show(Team $team, CalendarFeed $calendarFeed): CalendarFeedResource
    {
        Gate::authorize('view', $calendarFeed);

        return CalendarFeedResource::make($calendarFeed);
    }

    public function update(UpdateCalendarFeedRequest $request, Team $team, CalendarFeed $calendarFeed, UpdateFeed $updateFeed): CalendarFeedResource
    {
        return CalendarFeedResource::make($updateFeed->handle($calendarFeed, $request->validated()));
    }

    public function destroy(Team $team, CalendarFeed $calendarFeed): Response
    {
        Gate::authorize('delete', $calendarFeed);

        $calendarFeed->delete();

        return response()->noContent();
    }
}
