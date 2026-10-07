<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Calendar\CreateCalendarFeed;
use App\Mcp\Tools\Calendar\DeleteCalendarFeed;
use App\Mcp\Tools\Calendar\GetCalendarEvents;
use App\Mcp\Tools\Calendar\ListCalendarFeeds;
use App\Mcp\Tools\Calendar\UpdateCalendarFeed;
use App\Mcp\Tools\Inventory\CreateItem;
use App\Mcp\Tools\Inventory\DeleteItem;
use App\Mcp\Tools\Inventory\DuplicateItem;
use App\Mcp\Tools\Inventory\GetItem;
use App\Mcp\Tools\Inventory\MoveItemToTeam;
use App\Mcp\Tools\Inventory\RestoreItem;
use App\Mcp\Tools\Inventory\SearchItems;
use App\Mcp\Tools\Inventory\UpdateItem;
use App\Mcp\Tools\Lists\AddChecklistItems;
use App\Mcp\Tools\Lists\ClearCompletedChecklistItems;
use App\Mcp\Tools\Lists\CreateChecklist;
use App\Mcp\Tools\Lists\DeleteChecklist;
use App\Mcp\Tools\Lists\GetChecklist;
use App\Mcp\Tools\Lists\ListChecklists;
use App\Mcp\Tools\Lists\RemoveChecklistItem;
use App\Mcp\Tools\Lists\ResetChecklist;
use App\Mcp\Tools\Lists\UpdateChecklist;
use App\Mcp\Tools\Lists\UpdateChecklistItem;
use App\Mcp\Tools\Recipes\CopyRecipeToTeam;
use App\Mcp\Tools\Recipes\CreateRecipe;
use App\Mcp\Tools\Recipes\DeleteRecipe;
use App\Mcp\Tools\Recipes\GetRecipe;
use App\Mcp\Tools\Recipes\ImportRecipeFromUrl;
use App\Mcp\Tools\Recipes\RemixRecipe;
use App\Mcp\Tools\Recipes\SearchRecipes;
use App\Mcp\Tools\Recipes\UpdateRecipe;
use App\Mcp\Tools\Recipes\UpdateRecipeSharing;
use App\Mcp\Tools\Routines\AddRoutineSteps;
use App\Mcp\Tools\Routines\CompleteRoutineStep;
use App\Mcp\Tools\Routines\CreateRoutine;
use App\Mcp\Tools\Routines\DeleteRoutine;
use App\Mcp\Tools\Routines\GetRoutine;
use App\Mcp\Tools\Routines\GetRoutineBoard;
use App\Mcp\Tools\Routines\ListRoutines;
use App\Mcp\Tools\Routines\RemoveRoutineStep;
use App\Mcp\Tools\Routines\ReorderRoutineSteps;
use App\Mcp\Tools\Routines\UpdateRoutine;
use App\Mcp\Tools\Routines\UpdateRoutineStep;
use App\Mcp\Tools\Teams\ForgetKioskDevice;
use App\Mcp\Tools\Teams\GetTeamSettings;
use App\Mcp\Tools\Teams\ListKioskDevices;
use App\Mcp\Tools\Teams\ListTeams;
use App\Mcp\Tools\Teams\SwitchTeam;
use App\Mcp\Tools\Teams\UpdateTeam;
use App\Mcp\Tools\Teams\UpdateTeamSettings;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolSearch;

#[Name('Sunny')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Sunny is a family dashboard for managing recipes, home inventory, calendars,
    lists, and routines.

    Finding tools: the everyday tools are listed directly. Everything else (editing
    and deleting records, recipe sharing, calendar feeds, routine setup, teams, and
    settings) is in a catalog: use `search_tools` to find a tool and its input schema,
    then `execute_tools` to call it by its exact name.

    Teams: every tool acts on the authenticated user's current team. Use `list-teams`
    to see the user's teams and `switch-team` to change the current team; switching
    also changes the current team in the user's Sunny web app. Only the move/copy
    tools write to another team, and only to teams the user belongs to.

    Recipes: `ingredients` and `instructions` are stored as HTML (`<ul>`/`<ol>` lists).
    Plain text passed to the create/update tools is automatically wrapped into lists,
    one item per line.

    Inventory: items form a hierarchy via `parent_id`. An item's `type` is one of
    `location`, `bin`, or `item` (e.g. a shelf location contains bins, bins contain items).
    Deleted items can be found with `search-items` (`trashed: true`) and restored.

    Calendars: feeds are external iCal/ICS subscriptions; events are fetched live from
    the feed URLs, so event queries may take a moment.

    Lists: checklists are `todo`, `shopping`, or `wishlist` lists. A list with no
    owner (`user_id` null) belongs to the whole household.

    Routines: a routine is a recurring set of steps (daily, weekly on chosen weekdays,
    or monthly on a day). Weekdays are integers, 0 = Sunday through 6 = Saturday.
    `get-routine-board` shows a day's occurrences and their steps; tick a step off with
    `complete-routine-step` using its `occurrence_step_id`.
    MARKDOWN)]
class SunnyServer extends Server
{
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 100;

    /** @var array<int|class-string<ToolSearch>, class-string<Tool>|array<int, class-string<Tool>>> */
    protected array $tools = [
        SearchRecipes::class,
        GetRecipe::class,
        CreateRecipe::class,
        ImportRecipeFromUrl::class,
        SearchItems::class,
        GetItem::class,
        CreateItem::class,
        UpdateItem::class,
        GetCalendarEvents::class,
        ListChecklists::class,
        GetChecklist::class,
        CreateChecklist::class,
        AddChecklistItems::class,
        UpdateChecklistItem::class,
        ListRoutines::class,
        GetRoutineBoard::class,
        CompleteRoutineStep::class,
        ToolSearch::class => [
            UpdateRecipe::class,
            DeleteRecipe::class,
            RemixRecipe::class,
            CopyRecipeToTeam::class,
            UpdateRecipeSharing::class,
            DeleteItem::class,
            RestoreItem::class,
            DuplicateItem::class,
            MoveItemToTeam::class,
            ListCalendarFeeds::class,
            CreateCalendarFeed::class,
            UpdateCalendarFeed::class,
            DeleteCalendarFeed::class,
            UpdateChecklist::class,
            DeleteChecklist::class,
            RemoveChecklistItem::class,
            ClearCompletedChecklistItems::class,
            ResetChecklist::class,
            GetRoutine::class,
            CreateRoutine::class,
            UpdateRoutine::class,
            DeleteRoutine::class,
            AddRoutineSteps::class,
            UpdateRoutineStep::class,
            ReorderRoutineSteps::class,
            RemoveRoutineStep::class,
            ListTeams::class,
            SwitchTeam::class,
            UpdateTeam::class,
            GetTeamSettings::class,
            UpdateTeamSettings::class,
            ListKioskDevices::class,
            ForgetKioskDevice::class,
        ],
    ];

    /** @var array<int, class-string<Server\Resource>> */
    protected array $resources = [];

    /** @var array<int, class-string<Prompt>> */
    protected array $prompts = [];
}
