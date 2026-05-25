<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CalendarColor;
use App\Enums\ItemType;
use App\Models\CalendarFeed;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class StrawhatsSeeder extends Seeder
{
    public function run(): void
    {
        $strawhats = Team::factory()->create(['name' => 'Strawhat Pirates', 'slug' => 'strawhat-pirates']);
        $crew = User::factory()
            ->count(10)
            ->hasAttached($strawhats)
            ->sequence(
                ['name' => 'Monkey D. Luffy', 'email' => 'luffy@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Roronoa Zoro', 'email' => 'zoro@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Nami', 'email' => 'nami@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Usopp', 'email' => 'usopp@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Sanji', 'email' => 'sanji@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Tony Tony Chopper', 'email' => 'chopper@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Nico Robin', 'email' => 'robin@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Franky', 'email' => 'franky@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Brook', 'email' => 'brook@strawhat.pirates', 'current_team_id' => $strawhats->id],
                ['name' => 'Jinbe', 'email' => 'jinbe@strawhat.pirates', 'current_team_id' => $strawhats->id],
            )
            ->create();

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

        Recipe::factory()
            ->for($strawhats)
            ->count(43)
            ->sequence(
                ['name' => 'Gin\'s Takeout Stirfry', 'source' => 'inspired by Chapter 44'],
                ['name' => 'Unbelievably Awful (Great) Soup', 'source' => 'inspired by Chapter 67'],
                ['name' => 'Desert-Trekking Pirate Lunchbox', 'source' => 'inspired by Chapter 162'],
                ['name' => 'Split-the-Booty Sandwiches', 'source' => 'inspired by Chapter 302'],
                ['name' => 'Water 7\'s Mizu-Mizu Barbecue', 'source' => 'inspired by Chapter 433'],
                ['name' => 'Monster Sandora Lizard Roast', 'source' => 'inspired by Chapter 162'],
                ['name' => 'Luffy\'s Favorite - Meat on the Bone', 'source' => 'inspired by Chapter 69'],
                ['name' => 'Yagara Bull\'s Pick - Mizu-Mizu Steamed Meat', 'source' => 'inspired by Chapter 433'],
                ['name' => 'Impel Down Hummingbird Roast', 'source' => 'inspired by Chapter 530'],
                ['name' => 'Lakeside Camp Stone-Stew', 'source' => 'inspired by Chapter 253'],
                ['name' => 'Absalom\'s (!?) Croquette', 'source' => 'inspired by Chapter 463'],
                ['name' => 'Davy Back Fight Frankfurt', 'source' => 'inspired by Chapter 306'],
                ['name' => 'Sky Island Specialty Fruit - Sky Seafood Full Course', 'source' => 'inspired by Chapter 240'],
                ['name' => 'Blue-Finned Elephant Tuna Sautée', 'source' => 'inspired by Chapter 105'],
                ['name' => 'White Sea Dish - Skyfish Sautée', 'source' => 'inspired by Chapter 237'],
                ['name' => 'Saruyama Alliance Pike Full-Course', 'source' => 'inspired by Chapter 229'],
                ['name' => 'Sky Island-Bred Skyshark Roast', 'source' => 'inspired by Chapter 252'],
                ['name' => 'Mermaid Café Wakame Brûlée', 'source' => 'inspired by Chapter 610'],
                ['name' => 'Keimi\'s Yummy Clams', 'source' => 'inspired by Chapter 610'],
                ['name' => 'Great Side-Dish! Octopus Slices', 'source' => 'inspired by Chapter 83'],
                ['name' => 'Gold-Hunting Sky Island Lunchbox', 'source' => 'inspired by Chapter 253'],
                ['name' => 'The Water City\'s Mizu-Mizu Cabbage', 'source' => 'inspired by Chapter 326'],
                ['name' => 'The Isle of Women\'s Deathcap Mushroom', 'source' => 'inspired by Chapter 514'],
                ['name' => 'Yosaku\'s Pick - Bean Stirfry', 'source' => 'inspired by Chapter 69'],
                ['name' => 'Early Summer Potato Paille', 'source' => 'inspired by Chapter 322'],
                ['name' => 'Ex-Pirate Shakky\'s Baked Beans', 'source' => 'inspired by Chapter 498'],
                ['name' => 'Strawhats In a Bind! Monster Burger', 'source' => 'inspired by Chapter 312'],
                ['name' => 'Tom\'s Workers - Kokoro\'s Curry Rice', 'source' => 'inspired by Chapter 353'],
                ['name' => 'Davy Back Fight Stall Yakisoba', 'source' => 'inspired by Chapter 306'],
                ['name' => 'Davy Back Fight Free Inari Sushi', 'source' => 'inspired by Chapter 308'],
                ['name' => 'Davy Back Fight Free Kitsune Udon', 'source' => 'inspired by Chapter 308'],
                ['name' => 'Sea King Penne Gorgonzola', 'source' => 'inspired by Chapter 522'],
                ['name' => 'Ladies\' Never-Before-Seen Takoyaki', 'source' => 'inspired by Chapter 222'],
                ['name' => 'Mock Town Cherry Pie', 'source' => 'inspired by Chapter 223'],
                ['name' => 'Cindry-chan\'s Pudding', 'source' => 'inspired by Chapter 446'],
                ['name' => 'Gan Fall\'s Pumpkin Juice', 'source' => 'inspired by Chapter 248'],
                ['name' => 'Luffy and Zoro\'s Pick - Breadsticks', 'source' => 'inspired by Chapter 303'],
                ['name' => 'Try-Your-Luck Bomb Apples', 'source' => 'inspired by Chapter 223'],
                ['name' => 'Fruit Macedonia of Amends', 'source' => 'inspired by Chapter 46'],
                ['name' => 'Antonio\'s Graman (Grand Line Stickybuns)', 'source' => 'inspired by Chapter 497'],
                ['name' => 'Master Oda\'s Favorite - Chicken Onigiri!'],
                ['name' => 'Master Oda\'s Dinner Paparazzi! (Work)'],
                ['name' => 'Master Oda\'s Dinner Paparazzi! (Home)'],
            )
            ->create();

        CalendarFeed::factory()
            ->for($strawhats)
            ->count(10)
            ->sequence(
                ['name' => 'Brazilian Holidays (Luffy)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=BR&year=' . now('Y'), 'color' => CalendarColor::Green],
                ['name' => 'Japanese Holidays (Zoro)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=JP&year=' . now('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Swedish Holidays (Nami)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=SE&year=' . now('Y'), 'color' => CalendarColor::Gold],
                ['name' => 'South African Holidays (Usopp)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=ZA&year=' . now('Y'), 'color' => CalendarColor::Red],
                ['name' => 'French Holidays (Sanji)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=FR&year=' . now('Y'), 'color' => CalendarColor::Blue],
                ['name' => 'Canadian Holidays (Chopper)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=CA&year=' . now('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Russian Holidays (Robin)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=RU&year=' . now('Y'), 'color' => CalendarColor::Blue],
                ['name' => 'American Holidays (Franky)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=US&year=' . now('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Austrian Holidays (Brook)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=AT&year=' . now('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Indian Holidays (Jinbe)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=IN&year=' . now('Y'), 'color' => CalendarColor::Orange],
            )
            ->create();
    }
}
