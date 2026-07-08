<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Calendar\CreateCalendarFeed;
use App\Mcp\Tools\Calendar\GetCalendarEvents;
use App\Mcp\Tools\Calendar\ListCalendarFeeds;
use App\Mcp\Tools\Calendar\UpdateCalendarFeed;
use App\Mcp\Tools\Inventory\CreateItem;
use App\Mcp\Tools\Inventory\GetItem;
use App\Mcp\Tools\Inventory\SearchItems;
use App\Mcp\Tools\Inventory\UpdateItem;
use App\Mcp\Tools\Recipes\CreateRecipe;
use App\Mcp\Tools\Recipes\DeleteRecipe;
use App\Mcp\Tools\Recipes\GetRecipe;
use App\Mcp\Tools\Recipes\ImportRecipeFromUrl;
use App\Mcp\Tools\Recipes\SearchRecipes;
use App\Mcp\Tools\Recipes\UpdateRecipe;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Sunny')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Sunny is a family dashboard for managing recipes, home inventory, and calendars.

    All data is scoped to the authenticated user's current team; there is no way to
    read or write another team's data through this server.

    Recipes: `ingredients` and `instructions` are stored as HTML (`<ul>`/`<ol>` lists).
    Plain text passed to the create/update tools is automatically wrapped into lists,
    one item per line.

    Inventory: items form a hierarchy via `parent_id`. An item's `type` is one of
    `location`, `bin`, or `item` (e.g. a shelf location contains bins, bins contain items).

    Calendars: feeds are external iCal/ICS subscriptions; events are fetched live from
    the feed URLs, so event queries may take a moment.
    MARKDOWN)]
class SunnyServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        SearchRecipes::class,
        GetRecipe::class,
        CreateRecipe::class,
        UpdateRecipe::class,
        DeleteRecipe::class,
        ImportRecipeFromUrl::class,
        SearchItems::class,
        GetItem::class,
        CreateItem::class,
        UpdateItem::class,
        GetCalendarEvents::class,
        ListCalendarFeeds::class,
        CreateCalendarFeed::class,
        UpdateCalendarFeed::class,
    ];

    /** @var array<int, class-string<Server\Resource>> */
    protected array $resources = [];

    /** @var array<int, class-string<Prompt>> */
    protected array $prompts = [];
}
