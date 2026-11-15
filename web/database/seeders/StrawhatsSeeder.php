<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CalendarColor;
use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\CalendarFeed;
use App\Models\Checklist;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class StrawhatsSeeder extends Seeder
{
    public function run(): void
    {
        $strawhats = Team::factory()->create(['name' => 'Strawhat Pirates', 'slug' => 'strawhat-pirates']);
        $crew = User::factory()
            ->count(10)
            ->hasAttached($strawhats)
            ->sequence(
                ['name' => 'Monkey D. Luffy', 'email' => 'luffy@strawhat.pirates'],
                ['name' => 'Roronoa Zoro', 'email' => 'zoro@strawhat.pirates'],
                ['name' => 'Nami', 'email' => 'nami@strawhat.pirates'],
                ['name' => 'Usopp', 'email' => 'usopp@strawhat.pirates'],
                ['name' => 'Sanji', 'email' => 'sanji@strawhat.pirates'],
                ['name' => 'Tony Tony Chopper', 'email' => 'chopper@strawhat.pirates'],
                ['name' => 'Nico Robin', 'email' => 'robin@strawhat.pirates'],
                ['name' => 'Franky', 'email' => 'franky@strawhat.pirates'],
                ['name' => 'Brook', 'email' => 'brook@strawhat.pirates'],
                ['name' => 'Jinbe', 'email' => 'jinbe@strawhat.pirates'],
            )
            ->create();

        // The user factory switches each user to their personal team, so switch the crew back.
        $crew->each->switchTeam($strawhats);

        $locations = Item::factory()->for($strawhats)
            ->count(15)
            ->sequence(
                ['name' => 'Boys\' Room', 'type' => ItemType::Location],
                ['name' => 'Girls\' Room', 'type' => ItemType::Location],
                ['name' => 'Kitchen', 'type' => ItemType::Location],
                ['name' => 'Sick Bay', 'type' => ItemType::Location],
                ['name' => 'Aquarium Bar', 'type' => ItemType::Location],
                ['name' => 'Bathroom', 'type' => ItemType::Location],
                ['name' => 'Library', 'type' => ItemType::Location],
                ['name' => 'Usopp Factory', 'type' => ItemType::Location],
                ['name' => 'Franky\'s Workshop', 'type' => ItemType::Location],
                ['name' => 'Crow\'s Nest', 'type' => ItemType::Location],
                ['name' => 'Helm', 'type' => ItemType::Location],
                ['name' => 'Lawn', 'type' => ItemType::Location],
                ['name' => 'Garden Deck', 'type' => ItemType::Location],
                ['name' => 'Soldier Dock System', 'type' => ItemType::Location],
                ['name' => 'Energy Room', 'type' => ItemType::Location],
            )
            ->create();

        [$one, $two, $three, $four, $five, $six] = Item::factory()
            ->for($strawhats)
            ->for($locations->firstWhere('name', 'Soldier Dock System'), 'parent')
            ->count(6)
            ->sequence(
                ['name' => 'Channel 1', 'type' => ItemType::Location],
                ['name' => 'Channel 2', 'type' => ItemType::Location],
                ['name' => 'Channel 3', 'type' => ItemType::Location],
                ['name' => 'Channel 4', 'type' => ItemType::Location],
                ['name' => 'Channel 5', 'type' => ItemType::Location],
                ['name' => 'Channel 6', 'type' => ItemType::Location],
            )
            ->create();

        Item::factory()
            ->for($strawhats)
            ->count(6)
            ->sequence(
                ['name' => 'Shiro Mokuba I', 'type' => ItemType::Item, 'parent_id' => $one->id],
                ['name' => 'Mini Merry II', 'type' => ItemType::Item, 'parent_id' => $two->id],
                ['name' => 'Shark Submerge III', 'type' => ItemType::Item, 'parent_id' => $three->id],
                ['name' => 'Kurosai FR-U IV', 'type' => ItemType::Item, 'parent_id' => $four->id],
                ['name' => 'Brachio Tank V', 'type' => ItemType::Item, 'parent_id' => $five->id],
                ['name' => 'Inflatable Pool', 'type' => ItemType::Item, 'parent_id' => $six->id],
            )
            ->create();

        $this->seedRecipes($strawhats);

        CalendarFeed::factory()
            ->for($strawhats)
            ->count(10)
            ->sequence(
                ['name' => 'Brazilian Holidays (Luffy)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=BR&year=' . now()->format('Y'), 'color' => CalendarColor::Green],
                ['name' => 'Japanese Holidays (Zoro)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=JP&year=' . now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Swedish Holidays (Nami)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=SE&year=' . now()->format('Y'), 'color' => CalendarColor::Gold],
                ['name' => 'South African Holidays (Usopp)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=ZA&year=' . now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'French Holidays (Sanji)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=FR&year=' . now()->format('Y'), 'color' => CalendarColor::Blue],
                ['name' => 'Canadian Holidays (Chopper)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=CA&year=' . now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Russian Holidays (Robin)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=RU&year=' . now()->format('Y'), 'color' => CalendarColor::Blue],
                ['name' => 'American Holidays (Franky)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=US&year=' . now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Austrian Holidays (Brook)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=AT&year=' . now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Indian Holidays (Jinbe)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=IN&year=' . now()->format('Y'), 'color' => CalendarColor::Orange],
            )
            ->create();

        $this->seedRoutines($strawhats, $crew);
        $this->seedChecklists($strawhats, $crew);
    }

    private function seedRecipes(Team $team): void
    {
        /** @var array<int, array<string, mixed>> $recipes */
        $recipes = json_decode(
            File::get(database_path('seeders/data/recipes.json')),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($recipes as $recipe) {
            Recipe::factory()->for($team)->create($recipe);
        }
    }

    /** @param Collection<int, User> $crew */
    private function seedRoutines(Team $team, Collection $crew): void
    {
        $daily = ['frequency' => RoutineFrequency::Daily];
        $weekly = fn (array $days): array => ['frequency' => RoutineFrequency::Weekly, 'weekdays' => $days];
        $monthly = fn (int $day): array => ['frequency' => RoutineFrequency::Monthly, 'day_of_month' => $day];

        $this->routine($team, $crew->firstWhere('name', 'Monkey D. Luffy'), 'Morning', TimeOfDay::Morning, $daily, [
            'Eat breakfast',
            'Eat second breakfast',
            'Check the meat supply',
            'Stretch',
        ]);

        $this->routine($team, $crew->firstWhere('name', 'Roronoa Zoro'), 'Training', TimeOfDay::Morning, $daily, [
            'One thousand push-ups',
            'Lift the giant weights',
            'Nap in the crow\'s nest',
            'Find the way back to the deck',
        ]);

        $this->routine($team, $crew->firstWhere('name', 'Nami'), 'Navigation Check', TimeOfDay::Morning, $daily, [
            'Read the Log Pose',
            'Chart today\'s course',
            'Record the weather',
        ]);

        $this->routine($team, $crew->firstWhere('name', 'Sanji'), 'Galley Prep', TimeOfDay::Afternoon, $weekly([Carbon::MONDAY, Carbon::THURSDAY]), [
            'Inventory the pantry',
            'Prep the stock',
            'Sharpen the knives',
        ]);

        $this->routine($team, $crew->firstWhere('name', 'Franky'), 'Ship Maintenance', TimeOfDay::Anytime, $monthly(1), [
            'Inspect the Soldier Dock System',
            'Top up the cola reserves',
            'Check the paddle wheels',
        ]);

        $this->routine($team, null, 'Chores', TimeOfDay::Afternoon, $weekly([Carbon::SATURDAY]), [
            'Swab the deck',
            'Water the tangerine grove',
            'Do the laundry',
            'Restock the sick bay',
        ]);

        $this->routine($team, null, 'Night Watch', TimeOfDay::Evening, $daily, [
            'Climb to the crow\'s nest',
            'Check the Log Pose',
            'Douse the lamps',
        ]);

        // Paused, so the management screen has something in that state to show.
        $this->routine($team, $crew->firstWhere('name', 'Brook'), 'Afternoon Tea', TimeOfDay::Afternoon, $daily, [
            'Brew the tea',
            'Practice a new song',
        ], isActive: false);
    }

    /**
     * @param  array<int, string>  $steps
     */
    private function routine(
        Team $team,
        ?User $user,
        string $name,
        TimeOfDay $timeOfDay,
        array $schedule,
        array $steps,
        bool $isActive = true,
    ): void {
        $routine = Routine::factory()->for($team)->create([
            'user_id' => $user?->id,
            'name' => $name,
            'time_of_day' => $timeOfDay,
            'starts_on' => now()->subMonth(),
            'is_active' => $isActive,
            ...$schedule,
        ]);

        $routine->steps()->createMany(
            array_map(fn (string $step, int $index): array => ['name' => $step, 'position' => $index + 1], $steps, array_keys($steps)),
        );
    }

    /** @param Collection<int, User> $crew */
    private function seedChecklists(Team $team, Collection $crew): void
    {
        $this->checklist($team, null, 'Groceries', ChecklistType::Shopping, [
            'Meat',
            'More meat',
            'Tangerines',
            'Cola',
            'Rice',
            'Soy sauce',
            'Tea leaves',
        ], completed: 3, completedBy: $crew->firstWhere('name', 'Sanji'));

        $this->checklist($team, $crew->firstWhere('name', 'Tony Tony Chopper'), 'Sick Bay Restock', ChecklistType::Shopping, [
            'Bandages',
            'Rumble Balls',
            'Fever medicine',
            'Cotton swabs',
        ]);

        $this->checklist($team, $crew->firstWhere('name', 'Monkey D. Luffy'), 'Luffy\'s Wish List', ChecklistType::Wishlist, [
            'A mountain of meat',
            'A bigger mountain of meat',
            'To be King of the Pirates',
        ]);

        $this->checklist($team, $crew->firstWhere('name', 'Nico Robin'), 'Robin\'s Wish List', ChecklistType::Wishlist, [
            'The Rio Poneglyph',
            'A quiet afternoon in the library',
            'Coffee that Sanji did not over-sweeten',
        ]);

        $this->checklist($team, $crew->firstWhere('name', 'Franky'), 'Ship Repairs', ChecklistType::Todo, [
            'Patch the port hull',
            'Rewire the Coup de Burst',
            'Replace the mast rigging',
        ], completed: 1, completedBy: $crew->firstWhere('name', 'Franky'));

        $this->checklist($team, null, 'Before The Next Island', ChecklistType::Todo, [
            'Refill the water barrels',
            'Post the watch rotation',
            'Hide the emergency meat',
        ]);
    }

    /**
     * @param  array<int, string>  $items
     * @param  int  $completed  how many of the leading items are already checked off
     */
    private function checklist(
        Team $team,
        ?User $user,
        string $name,
        ChecklistType $type,
        array $items,
        int $completed = 0,
        ?User $completedBy = null,
    ): void {
        $checklist = Checklist::factory()->for($team)->create([
            'user_id' => $user?->id,
            'name' => $name,
            'type' => $type,
        ]);

        foreach ($items as $index => $item) {
            $created = $checklist->items()->create(['name' => $item, 'position' => $index + 1]);

            if ($index < $completed) {
                $created->complete($completedBy);
            }
        }
    }
}
