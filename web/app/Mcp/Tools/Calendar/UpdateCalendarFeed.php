<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use App\Actions\Kiosk\UpdateFeed;
use App\Enums\CalendarColor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update an existing calendar feed\'s name, URL, or color. Only the provided fields are changed. Use the list-calendar-feeds tool to find feed IDs.')]
class UpdateCalendarFeed extends Tool
{
    public function handle(Request $request, UpdateFeed $updateFeed): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'string', 'url:http,https'],
            'color' => ['sometimes', Rule::enum(CalendarColor::class)],
        ], [
            'url.url' => 'The url must be a valid http or https URL pointing to an ICS calendar file.',
            'color.enum' => 'The color must be one of: ' . collect(CalendarColor::cases())->map(fn (CalendarColor $color): string => "{$color->value} ({$color->name})")->implode(', ') . '.',
        ]);

        $feed = $request->user()->currentTeam->calendarFeeds()->find($validated['id']);

        if ($feed === null || Gate::denies('update', $feed)) {
            return Response::error('Calendar feed not found.');
        }

        $data = Arr::only($validated, ['name', 'url', 'color']);

        if ($data === []) {
            return Response::error('Provide at least one field to update: name, url, or color.');
        }

        $feed = $updateFeed->handle($feed, $data);

        return Response::text("Calendar feed \"{$feed->name}\" (ID {$feed->id}) updated.");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The ID of the calendar feed to update. Use the list-calendar-feeds tool to find feed IDs.')
                ->required(),
            'name' => $schema->string()
                ->max(255)
                ->description('A new display name for the calendar feed.'),
            'url' => $schema->string()
                ->description('A new http(s) URL of the ICS (iCalendar) file for this feed.'),
            'color' => $schema->string()
                ->enum(array_column(CalendarColor::cases(), 'value'))
                ->description('A new display color for this feed\'s events. One of: ' . collect(CalendarColor::cases())->map(fn (CalendarColor $color): string => "{$color->value} ({$color->name})")->implode(', ') . '.'),
        ];
    }
}
