<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use App\Actions\Kiosk\CreateFeed;
use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld]
#[Description('Add a new ICS calendar feed to the team\'s calendar. The feed URL must point to an ICS (iCalendar) file, such as a Google Calendar secret address or an Outlook published calendar.')]
class CreateCalendarFeed extends Tool
{
    public function handle(Request $request, CreateFeed $createFeed): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url:http,https'],
            'color' => ['required', Rule::enum(CalendarColor::class)],
        ], [
            'url.url' => 'The url must be a valid http or https URL pointing to an ICS calendar file.',
            'color.enum' => 'The color must be one of: ' . collect(CalendarColor::cases())->map(fn (CalendarColor $color): string => "{$color->value} ({$color->name})")->implode(', ') . '.',
        ]);

        if (Gate::denies('create', CalendarFeed::class)) {
            return Response::error('You are not allowed to create calendar feeds.');
        }

        $feed = $createFeed->handle($request->user()->currentTeam, $validated);

        return Response::text("Calendar feed \"{$feed->name}\" created with ID {$feed->id}.");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->max(255)
                ->description('A display name for the calendar feed, for example "Family" or "Work".')
                ->required(),
            'url' => $schema->string()
                ->description('The http(s) URL of the ICS (iCalendar) file for this feed.')
                ->required(),
            'color' => $schema->string()
                ->enum(array_column(CalendarColor::cases(), 'value'))
                ->description('The display color for this feed\'s events. One of: ' . collect(CalendarColor::cases())->map(fn (CalendarColor $color): string => "{$color->value} ({$color->name})")->implode(', ') . '.')
                ->required(),
        ];
    }
}
