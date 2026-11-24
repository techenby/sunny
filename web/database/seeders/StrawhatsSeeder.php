<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Enums\CalendarColor;
use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TeamRole;
use App\Enums\TimeOfDay;
use App\Models\CalendarFeed;
use App\Models\Checklist;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Membership;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        $this->seedCalendars($strawhats);

        $this->seedRoutines($strawhats, $crew);
        $this->seedChecklists($strawhats, $crew);

        $nami = $crew->firstWhere('name', 'Nami');
        $this->seedRoles($strawhats, $crew);
        $this->seedInventory($strawhats, $locations);
        $this->seedRecipePhotos($strawhats);
        $this->seedTodaysProgress($strawhats, $crew);
        $this->seedBaratie($crew);

        TeamInvitation::factory()->for($strawhats)->create([
            'email' => 'vivi@alabasta.kingdom',
            'invited_by' => $nami->id,
            'expires_at' => now()->addDays(3),
        ]);
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

        $this->routine($team, $crew->firstWhere('name', 'Usopp'), 'Lookout Duty', TimeOfDay::Evening, $weekly([(now()->dayOfWeek + 3) % 7]), []);
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
            ['Rumble Balls', 'https://example.com/rumble-balls'],
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

        $this->checklist($team, $crew->firstWhere('name', 'Nami'), 'Treasure To Find', ChecklistType::Wishlist, []);
    }

    /**
     * @param  array<int, string|array{string, string}>  $items
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
            [$name, $url] = is_array($item) ? $item : [$item, null];
            $created = $checklist->items()->create(['name' => $name, 'url' => $url, 'position' => $index + 1]);

            if ($index < $completed) {
                $created->complete($completedBy);
            }
        }
    }

    /** @param Collection<int, User> $crew */
    private function seedRoles(Team $team, Collection $crew): void
    {
        foreach (['Monkey D. Luffy' => TeamRole::Owner, 'Nami' => TeamRole::Admin] as $name => $role) {
            Membership::query()
                ->where('team_id', $team->id)
                ->where('user_id', $crew->firstWhere('name', $name)->id)
                ->update(['role' => $role]);
        }
    }

    private function seedCalendars(Team $team): void
    {
        $today = CarbonImmutable::now($team->timezone)->startOfDay();

        $calendars = [
            ['Crew', CalendarColor::Red, [
                [0, '09:00', '10:30', 'Navigation lesson', "Girls' Room"],
                [0, '18:00', '20:00', 'Dinner at the Baratie', 'Baratie'],
                [1, '14:30', '15:00', 'Check-up with Chopper', 'Sick Bay'],
                [2, null, null, 'Arrive at Water 7', null],
                [5, '19:00', '21:30', 'Soul King concert', 'Aquarium Bar'],
                [9, '10:00', '12:00', 'Treasure hunt briefing', 'Library'],
                [14, null, null, 'Shore leave', null],
            ]],
            ['Galley', CalendarColor::Green, [
                [0, '12:00', '13:00', 'Lunch: Sea King steaks', 'Kitchen'],
                [3, '07:00', '08:00', 'Market run', 'Port town'],
                [8, '16:00', '17:00', 'Tangerine harvest', 'Garden Deck'],
            ]],
            ['Training', CalendarColor::Blue, [
                [1, '06:00', '07:30', 'Sparring practice', 'Lawn'],
                [4, '06:00', '07:30', 'Sparring practice', 'Lawn'],
                [11, '15:00', '16:00', 'Sniper drills', 'Crow\'s Nest'],
            ]],
        ];

        foreach ($calendars as [$name, $color, $events]) {
            $feed = CalendarFeed::factory()->for($team)->create([
                'name' => $name,
                'url' => 'https://calendar.strawhat.pirates/' . Str::slug($name) . '.ics',
                'color' => $color,
            ]);

            Storage::put($feed->icsPath(), $this->ics($name, $today, $events));
            $feed->forceFill(['ics_fetched_at' => now(), 'last_fetched_at' => now()])->save();
        }

        CalendarFeed::factory()->for($team)->create([
            'name' => 'Marine Patrols',
            'url' => 'https://calendar.strawhat.pirates/marine-patrols.ics',
            'color' => CalendarColor::Orange,
        ])->forceFill([
            'last_failed_at' => now()->subHours(2),
            'last_error' => __('The calendar server responded with HTTP :status.', ['status' => 404]),
        ])->save();
    }

    /** @param array<int, array{int, ?string, ?string, string, ?string}> $events */
    private function ics(string $name, CarbonImmutable $today, array $events): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Sunny//Strawhats//EN', 'X-WR-CALNAME:' . $name];

        foreach ($events as $index => [$days, $start, $end, $title, $location]) {
            $date = $today->addDays($days);
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . Str::slug($name) . "-{$index}@strawhat.pirates";
            $lines[] = 'DTSTAMP:' . now('UTC')->format('Ymd\THis\Z');

            if ($start === null) {
                $lines[] = 'DTSTART;VALUE=DATE:' . $date->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:' . $date->addDay()->format('Ymd');
            } else {
                $lines[] = 'DTSTART:' . $date->setTimeFromTimeString($start)->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTEND:' . $date->setTimeFromTimeString($end)->utc()->format('Ymd\THis\Z');
            }

            $lines[] = 'SUMMARY:' . $title;

            if ($location) {
                $lines[] = 'LOCATION:' . $location;
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /** @param Collection<int, Item> $locations */
    private function seedInventory(Team $team, Collection $locations): void
    {
        $desk = $this->item($team, 'Nami\'s Desk', ItemType::Bin, $locations->firstWhere('name', 'Girls\' Room'));
        $this->item($team, 'Log Pose', ItemType::Item, $desk, 'log-pose', [
            'Notes' => 'Points to the next island once it has recorded this one\'s magnetic field.',
            'Bought in' => 'Reverse Mountain',
        ]);
        $this->item($team, 'Clima-Tact', ItemType::Item, $desk, 'clima-tact', ['Made by' => 'Usopp']);
        $this->item($team, 'Den Den Mushi', ItemType::Item, $desk, 'den-den-mushi');
        $this->item($team, 'Map of the Grand Line', ItemType::Item, $desk);
        $this->item($team, 'Cracked Den Den Mushi', ItemType::Item, $desk)->delete();

        $kitchen = $locations->firstWhere('name', 'Kitchen');
        $fridge = $this->item($team, 'Fridge', ItemType::Bin, $kitchen);
        foreach (['Sea King steaks', 'Tangerine juice', 'Cola bottles', 'Leftover curry'] as $name) {
            $this->item($team, $name, ItemType::Item, $fridge);
        }
        $spices = $this->item($team, 'Spice Rack', ItemType::Bin, $kitchen);
        foreach (['Black pepper', 'Paprika', 'Saffron'] as $name) {
            $this->item($team, $name, ItemType::Item, $spices);
        }

        $chest = $this->item($team, 'Tool Chest', ItemType::Bin, $locations->firstWhere('name', 'Franky\'s Workshop'), 'tool-chest');
        $drawer = $this->item($team, 'Top Drawer', ItemType::Bin, $chest);
        foreach (['Rivets', 'Spare bolts', 'Wrench'] as $name) {
            $this->item($team, $name, ItemType::Item, $drawer);
        }
        foreach (['Blueprints', 'Clamps', 'Cola funnel', 'Drill', 'Hammer', 'Measuring tape', 'Paint brushes', 'Pliers', 'Sandpaper', 'Saw', 'Screwdrivers', 'Welding torch'] as $name) {
            $this->item($team, $name, ItemType::Item, $chest);
        }

        $this->item($team, 'Straw Hat', ItemType::Item, null, 'straw-hat', [
            'Previous owner' => 'Red-Haired Shanks',
            'Notes' => 'Handle with care. Do not lose.',
        ]);
        $this->item($team, 'Spare Cola Barrel', ItemType::Item, null, 'cola-barrel');
    }

    /** @param array<string, string> $metadata */
    private function item(Team $team, string $name, ItemType $type, ?Item $parent, ?string $photo = null, array $metadata = []): Item
    {
        $item = Item::factory()->for($team)->create([
            'name' => $name,
            'type' => $type,
            'parent_id' => $parent?->id,
            'metadata' => $metadata ?: null,
        ]);

        if ($photo) {
            $path = "teams/{$team->id}/items/{$item->id}-{$photo}.jpg";
            Storage::put($path, File::get(database_path("seeders/data/photos/{$photo}.jpg")));
            ItemPhoto::factory()->for($item)->create(['path' => $path, 'thumb_path' => $path]);
        }

        return $item;
    }

    private function seedRecipePhotos(Team $team): void
    {
        $photos = [
            'Luffy\'s Favorite Meat on the Bone' => 'meat-on-the-bone',
            'Kokoro\'s Curry Rice' => 'curry-rice',
            'Mock Town Cherry Pie' => 'cherry-pie',
            'Sea King Penne Gorgonzola' => 'penne-gorgonzola',
        ];

        foreach ($photos as $name => $photo) {
            $recipe = $team->recipes()->where('name', $name)->firstOrFail();
            $path = "teams/{$team->id}/recipes/{$recipe->id}-{$photo}.jpg";
            Storage::put($path, File::get(database_path("seeders/data/photos/{$photo}.jpg")));
            $recipe->forceFill(['photo_path' => $path, 'thumb_path' => $path])->save();
        }

        $team->recipes()->where('name', 'Luffy\'s Favorite Meat on the Bone')->firstOrFail()->enableSharing();
    }

    /** @param Collection<int, User> $crew */
    private function seedTodaysProgress(Team $team, Collection $crew): void
    {
        resolve(GenerateRoutineOccurrences::class)->handle($team);

        $luffy = $crew->firstWhere('name', 'Monkey D. Luffy');
        $occurrence = RoutineOccurrence::query()
            ->whereRelation('routine', 'name', 'Morning')
            ->whereDate('due_on', $team->today())
            ->firstOrFail();

        foreach ($occurrence->steps()->orderBy('id')->take(2)->get() as $step) {
            $step->forceFill(['completed_at' => now(), 'completed_by' => $luffy->id])->save();
        }
    }

    /** @param Collection<int, User> $crew */
    private function seedBaratie(Collection $crew): void
    {
        $baratie = Team::factory()->create(['name' => 'Baratie', 'slug' => 'baratie']);

        foreach (['Sanji' => TeamRole::Owner, 'Nami' => TeamRole::Member, 'Roronoa Zoro' => TeamRole::Member] as $name => $role) {
            Membership::create(['team_id' => $baratie->id, 'user_id' => $crew->firstWhere('name', $name)->id, 'role' => $role]);
        }

        /** @var array<int, array<string, mixed>> $recipes */
        $recipes = json_decode(File::get(database_path('seeders/data/recipes.json')), associative: true, flags: JSON_THROW_ON_ERROR);

        foreach ($recipes as $recipe) {
            if (in_array($recipe['name'], ['Gin\'s Takeout Stir-Fry', 'Sea King Penne Gorgonzola'], true)) {
                Recipe::factory()->for($baratie)->create($recipe);
            }
        }

        $galley = $this->item($baratie, 'Galley', ItemType::Location, null);
        $this->item($baratie, 'Zeff\'s Cookbook', ItemType::Item, $galley);
    }
}
