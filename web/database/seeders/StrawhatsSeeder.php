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
                ['name' => 'Brazilian Holidays (Luffy)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=BR&year='.now()->format('Y'), 'color' => CalendarColor::Green],
                ['name' => 'Japanese Holidays (Zoro)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=JP&year='.now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Swedish Holidays (Nami)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=SE&year='.now()->format('Y'), 'color' => CalendarColor::Gold],
                ['name' => 'South African Holidays (Usopp)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=ZA&year='.now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'French Holidays (Sanji)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=FR&year='.now()->format('Y'), 'color' => CalendarColor::Blue],
                ['name' => 'Canadian Holidays (Chopper)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=CA&year='.now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Russian Holidays (Robin)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=RU&year='.now()->format('Y'), 'color' => CalendarColor::Blue],
                ['name' => 'American Holidays (Franky)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=US&year='.now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Austrian Holidays (Brook)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=AT&year='.now()->format('Y'), 'color' => CalendarColor::Red],
                ['name' => 'Indian Holidays (Jinbe)', 'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=IN&year='.now()->format('Y'), 'color' => CalendarColor::Orange],
            )
            ->create();

        $this->seedRoutines($strawhats, $crew);
        $this->seedChecklists($strawhats, $crew);
    }

    private function seedRecipes(Team $team): void
    {
        $recipes = [
            [
                'name' => 'Luffy\'s Favorite Meat on the Bone',
                'source' => 'Inspired by One Piece, Chapter 69',
                'servings' => '4',
                'prep_time' => '25 minutes',
                'cook_time' => '35 minutes',
                'total_time' => '1 hour',
                'description' => 'Juicy chicken drumsticks wrapped around a savory boiled egg and glazed with a sweet soy sauce.',
                'ingredients' => '<ul><li>4 chicken drumsticks</li><li>4 hard-boiled eggs, peeled</li><li>1 pound ground chicken</li><li>1/2 cup panko breadcrumbs</li><li>1 egg, beaten</li><li>2 tablespoons soy sauce</li><li>1 tablespoon honey</li><li>1 teaspoon grated ginger</li><li>Salt and black pepper</li></ul>',
                'instructions' => '<ol><li>Heat the oven to 400°F and line a sheet pan with parchment.</li><li>Push the chicken meat down each drumstick to expose the bone.</li><li>Season the ground chicken, then mix it with the panko and beaten egg.</li><li>Wrap each boiled egg in the chicken mixture and press it firmly around a drumstick.</li><li>Roast for 25 minutes. Brush with soy sauce, honey, and ginger, then roast for 10 minutes more.</li><li>Rest for 5 minutes before serving.</li></ol>',
                'notes' => 'Chill the wrapped drumsticks for 15 minutes before roasting if the mixture feels loose.',
                'nutrition' => 'Approximately 520 calories, 45g protein, 19g carbohydrates, and 28g fat per serving.',
                'tags' => ['Dinner', 'Chicken', 'Crew Favorites'],
            ],
            [
                'name' => 'Gin\'s Takeout Stir-Fry',
                'source' => 'Inspired by One Piece, Chapter 44',
                'servings' => '4',
                'prep_time' => '15 minutes',
                'cook_time' => '12 minutes',
                'total_time' => '27 minutes',
                'description' => 'A fast, filling noodle stir-fry with pork, crisp vegetables, and a savory-sweet sauce.',
                'ingredients' => '<ul><li>8 ounces yakisoba noodles</li><li>8 ounces thinly sliced pork loin</li><li>2 cups shredded cabbage</li><li>1 carrot, cut into matchsticks</li><li>1/2 yellow onion, sliced</li><li>2 tablespoons neutral oil</li><li>3 tablespoons soy sauce</li><li>2 tablespoons Worcestershire sauce</li><li>1 tablespoon ketchup</li><li>1 teaspoon brown sugar</li></ul>',
                'instructions' => '<ol><li>Whisk together the soy sauce, Worcestershire sauce, ketchup, and brown sugar.</li><li>Heat one tablespoon oil in a wok and brown the pork. Transfer it to a plate.</li><li>Add the remaining oil, cabbage, carrot, and onion; stir-fry until just tender.</li><li>Add the noodles, pork, and sauce. Toss over high heat until glossy and hot.</li><li>Divide among bowls and serve immediately.</li></ol>',
                'notes' => 'A handful of bean sprouts or sliced mushrooms can be added with the cabbage.',
                'nutrition' => 'Approximately 460 calories, 25g protein, 55g carbohydrates, and 16g fat per serving.',
                'tags' => ['Dinner', 'Noodles', 'Quick'],
            ],
            [
                'name' => 'Kokoro\'s Curry Rice',
                'source' => 'Inspired by One Piece, Chapter 353',
                'servings' => '6',
                'prep_time' => '20 minutes',
                'cook_time' => '50 minutes',
                'total_time' => '1 hour 10 minutes',
                'description' => 'A comforting Japanese-style beef curry loaded with potatoes and carrots and served over steamed rice.',
                'ingredients' => '<ul><li>1 1/2 pounds beef chuck, cut into cubes</li><li>1 yellow onion, diced</li><li>2 carrots, cut into chunks</li><li>2 russet potatoes, cut into chunks</li><li>1 tablespoon grated ginger</li><li>2 cloves garlic, minced</li><li>4 cups beef stock</li><li>4 ounces Japanese curry roux</li><li>1 tablespoon neutral oil</li><li>6 cups cooked short-grain rice</li></ul>',
                'instructions' => '<ol><li>Heat the oil in a heavy pot and brown the beef in batches.</li><li>Add the onion, ginger, and garlic; cook until fragrant.</li><li>Return the beef to the pot with the carrots, potatoes, and stock.</li><li>Simmer, partially covered, for 35 minutes or until the beef is tender.</li><li>Turn off the heat, stir in the curry roux, then simmer gently for 10 minutes until thick.</li><li>Serve alongside steamed rice.</li></ol>',
                'notes' => 'The curry tastes even better the next day. Add a splash of stock when reheating.',
                'nutrition' => 'Approximately 610 calories, 31g protein, 76g carbohydrates, and 20g fat per serving.',
                'tags' => ['Dinner', 'Beef', 'Comfort Food'],
            ],
            [
                'name' => 'Ladies\' Never-Before-Seen Takoyaki',
                'source' => 'Inspired by One Piece, Chapter 222',
                'servings' => '24 pieces',
                'prep_time' => '20 minutes',
                'cook_time' => '20 minutes',
                'total_time' => '40 minutes',
                'description' => 'Crisp-edged, custardy octopus dumplings finished with tangy sauce, mayonnaise, and bonito flakes.',
                'ingredients' => '<ul><li>1 cup all-purpose flour</li><li>2 large eggs</li><li>2 1/2 cups dashi</li><li>1 tablespoon soy sauce</li><li>6 ounces cooked octopus, diced</li><li>3 scallions, thinly sliced</li><li>1/4 cup pickled ginger, chopped</li><li>Takoyaki sauce</li><li>Japanese mayonnaise</li><li>Bonito flakes and aonori</li></ul>',
                'instructions' => '<ol><li>Whisk the flour, eggs, dashi, and soy sauce into a thin batter.</li><li>Heat and generously oil a takoyaki pan.</li><li>Fill each well with batter, then add octopus, scallions, and pickled ginger.</li><li>As the edges set, turn each piece a quarter turn with skewers. Keep turning until round and browned.</li><li>Top with takoyaki sauce, mayonnaise, bonito flakes, and aonori.</li></ol>',
                'notes' => 'Keep the pan well oiled and do not worry about spilled batter; tuck it into the wells as you turn each piece.',
                'nutrition' => 'Approximately 290 calories, 14g protein, 39g carbohydrates, and 9g fat per 6-piece serving.',
                'tags' => ['Snack', 'Seafood', 'Japanese'],
            ],
            [
                'name' => 'Mock Town Cherry Pie',
                'source' => 'Inspired by One Piece, Chapter 223',
                'servings' => '8',
                'prep_time' => '30 minutes',
                'cook_time' => '55 minutes',
                'total_time' => '2 hours 25 minutes',
                'description' => 'A classic lattice-topped pie with a bright cherry filling and a crisp, buttery crust.',
                'ingredients' => '<ul><li>2 refrigerated pie crusts</li><li>5 cups pitted sour cherries</li><li>3/4 cup granulated sugar</li><li>1/4 cup cornstarch</li><li>1 tablespoon lemon juice</li><li>1 teaspoon vanilla extract</li><li>1/4 teaspoon almond extract</li><li>1 tablespoon unsalted butter</li><li>1 egg beaten with 1 tablespoon water</li></ul>',
                'instructions' => '<ol><li>Heat the oven to 425°F and fit one crust into a 9-inch pie plate.</li><li>Toss the cherries with sugar, cornstarch, lemon juice, vanilla, and almond extract.</li><li>Fill the crust, dot with butter, and cover with a lattice made from the second crust.</li><li>Brush with egg wash and bake for 20 minutes.</li><li>Reduce the heat to 375°F and bake for 35 minutes more.</li><li>Cool for at least 1 hour before slicing.</li></ol>',
                'notes' => 'Place the pie on a foil-lined sheet pan to catch any bubbling filling.',
                'nutrition' => 'Approximately 410 calories, 4g protein, 64g carbohydrates, and 17g fat per slice.',
                'tags' => ['Dessert', 'Baking', 'Fruit'],
            ],
            [
                'name' => 'Gan Fall\'s Spiced Pumpkin Juice',
                'source' => 'Inspired by One Piece, Chapter 248',
                'servings' => '4',
                'prep_time' => '5 minutes',
                'cook_time' => '5 minutes',
                'total_time' => '10 minutes',
                'description' => 'A creamy pumpkin drink with apple, orange, warming spices, and just enough maple sweetness.',
                'ingredients' => '<ul><li>1 cup pumpkin purée</li><li>2 cups apple cider</li><li>1 cup orange juice</li><li>2 tablespoons maple syrup</li><li>1/2 teaspoon ground cinnamon</li><li>1/4 teaspoon ground ginger</li><li>Pinch of ground nutmeg</li><li>1 teaspoon vanilla extract</li></ul>',
                'instructions' => '<ol><li>Combine all ingredients in a blender and blend until completely smooth.</li><li>For a warm drink, heat gently in a saucepan without boiling.</li><li>For a cold drink, chill for 1 hour and serve over ice.</li><li>Stir before pouring and garnish with cinnamon.</li></ol>',
                'notes' => 'Use unsweetened pumpkin purée, not pumpkin pie filling.',
                'nutrition' => 'Approximately 150 calories, 2g protein, 37g carbohydrates, and 1g fat per serving.',
                'tags' => ['Drinks', 'Vegetarian', 'Fall'],
            ],
            [
                'name' => 'Fruit Macedonia of Amends',
                'source' => 'Inspired by One Piece, Chapter 46',
                'servings' => '6',
                'prep_time' => '20 minutes',
                'cook_time' => '0 minutes',
                'total_time' => '50 minutes',
                'description' => 'A colorful fruit salad tossed in a light citrus-mint syrup for an easy galley dessert.',
                'ingredients' => '<ul><li>2 oranges, peeled and segmented</li><li>2 cups pineapple chunks</li><li>2 kiwis, peeled and sliced</li><li>1 cup strawberries, quartered</li><li>1 cup seedless grapes, halved</li><li>1 mango, diced</li><li>2 tablespoons honey</li><li>2 tablespoons lime juice</li><li>2 tablespoons chopped fresh mint</li></ul>',
                'instructions' => '<ol><li>Place all prepared fruit in a large serving bowl.</li><li>Whisk the honey and lime juice until smooth.</li><li>Pour the dressing over the fruit and fold gently to combine.</li><li>Chill for 30 minutes, then add the mint just before serving.</li></ol>',
                'notes' => 'Add bananas only at serving time so they do not brown or soften.',
                'nutrition' => 'Approximately 170 calories, 2g protein, 43g carbohydrates, and less than 1g fat per serving.',
                'tags' => ['Dessert', 'Fruit', 'Vegan', 'Gluten Free'],
            ],
            [
                'name' => 'Sea King Penne Gorgonzola',
                'source' => 'Inspired by One Piece, Chapter 522',
                'servings' => '4',
                'prep_time' => '10 minutes',
                'cook_time' => '20 minutes',
                'total_time' => '30 minutes',
                'description' => 'Penne in a rich gorgonzola cream sauce balanced with spinach, lemon, and toasted walnuts.',
                'ingredients' => '<ul><li>12 ounces penne</li><li>1 tablespoon unsalted butter</li><li>1 shallot, minced</li><li>1 cup heavy cream</li><li>5 ounces gorgonzola, crumbled</li><li>3 cups baby spinach</li><li>1 teaspoon lemon zest</li><li>1/3 cup toasted walnuts, chopped</li><li>Salt and black pepper</li></ul>',
                'instructions' => '<ol><li>Cook the penne in well-salted water until al dente, reserving one cup of pasta water.</li><li>Melt the butter in a skillet and cook the shallot until soft.</li><li>Add the cream and simmer for 3 minutes. Stir in the gorgonzola until smooth.</li><li>Add the pasta and spinach, tossing until the spinach wilts.</li><li>Loosen with pasta water as needed, then finish with lemon zest and walnuts.</li></ol>',
                'notes' => 'Use a mild, creamy gorgonzola dolce for a balanced sauce.',
                'nutrition' => 'Approximately 720 calories, 24g protein, 75g carbohydrates, and 38g fat per serving.',
                'tags' => ['Dinner', 'Pasta', 'Vegetarian'],
            ],
        ];

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
