<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use App\Models\CalendarFeed;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the team\'s calendar feeds, including each feed\'s ID, name, URL, color, when it was last fetched, and whether it is currently failing (with the last error message).')]
class ListCalendarFeeds extends Tool
{
    public function handle(Request $request): Response
    {
        $feeds = $request->user()->currentTeam->calendarFeeds()->get();

        if ($feeds->isEmpty()) {
            return Response::text('No calendar feeds have been added yet. Use the create-calendar-feed tool to add one.');
        }

        $lines = $feeds->map(fn (CalendarFeed $feed): string => implode("\n", array_filter([
            "## {$feed->name} (ID: {$feed->id})",
            "- URL: {$feed->url}",
            "- Color: {$feed->color->name} ({$feed->color->value})",
            '- Last fetched: ' . ($feed->last_fetched_at?->toDayDateTimeString() ?? 'never'),
            '- Status: ' . ($feed->isFailing() ? 'failing' : 'ok'),
            $feed->isFailing() ? "- Last error: {$feed->last_error}" : null,
        ])));

        return Response::text($lines->implode("\n\n"));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
