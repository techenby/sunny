<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Permanently remove a calendar feed subscription from the current team by id. Its events will no longer appear in get-calendar-events. Use the list-calendar-feeds tool to find feed IDs.')]
class DeleteCalendarFeed extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $feed = $request->user()->currentTeam->calendarFeeds()->find($validated['id']);

        if ($feed === null) {
            return Response::error('Calendar feed not found.');
        }

        Gate::forUser($request->user())->authorize('delete', $feed);

        $feed->delete();

        return Response::text("Calendar feed \"{$feed->name}\" (ID {$feed->id}) deleted.");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The ID of the calendar feed to delete. Use the list-calendar-feeds tool to find feed IDs.')
                ->required(),
        ];
    }
}
