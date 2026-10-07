<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the team\'s calendar feeds, including each feed\'s ID, name, URL, color, when it was last fetched, and whether it is currently failing (with the last error message).')]
class ListCalendarFeeds extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $feeds = $request->user()->currentTeam->calendarFeeds()->get();

        if ($feeds->isEmpty()) {
            return Response::structured([
                'feeds' => [],
                'note' => 'No calendar feeds have been added yet. Use the create-calendar-feed tool to add one.',
            ]);
        }

        return Response::structured([
            'feeds' => $feeds->map(fn (CalendarFeed $feed): array => [
                'id' => $feed->id,
                'name' => $feed->name,
                'url' => $feed->url,
                'color' => $feed->color->value,
                'color_name' => $feed->color->name,
                'last_fetched_at' => $feed->last_fetched_at?->toIso8601String(),
                'status' => $feed->isFailing() ? 'failing' : 'ok',
                'last_error' => $feed->isFailing() ? $feed->last_error : null,
            ])->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'feeds' => $schema->array()->items($schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
                'url' => $schema->string()->required(),
                'color' => $schema->string()->enum(CalendarColor::class)->required(),
                'color_name' => $schema->string()->required(),
                'last_fetched_at' => $schema->string()->nullable()->required()
                    ->description('Null if the feed has never been fetched.'),
                'status' => $schema->string()->enum(['ok', 'failing'])->required(),
                'last_error' => $schema->string()->nullable()->required(),
            ]))->required(),
            'note' => $schema->string(),
        ];
    }
}
