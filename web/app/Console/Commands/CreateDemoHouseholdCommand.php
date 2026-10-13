<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Inventory\CreateItem;
use App\Actions\Recipes\CreateRecipe;
use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Actions\Teams\CreateTeam;
use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TeamRole;
use App\Enums\TimeOfDay;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;

#[Description('Create a demo account with a household full of sample recipes, inventory, lists and routines')]
#[Signature('demo:household {email : Email address to sign in with} {--password= : Password to sign in with (prompted for when omitted)} {--timezone=America/Chicago : Household timezone}')]
class CreateDemoHouseholdCommand extends Command
{
    public function handle(CreateTeam $createTeam, CreateRecipe $createRecipe, CreateItem $createItem, GenerateRoutineOccurrences $occurrences): int
    {
        $email = (string) $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->components->error("A user with the email {$email} already exists.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?? password('Password for the demo account', required: true);

        $team = DB::transaction(function () use ($email, $password, $createTeam, $createRecipe, $createItem) {
            $user = $this->member('Alex Rivera', $email, $password);

            $team = $createTeam->handle($user, 'The Rivera Family');
            $team->update(['timezone' => $this->option('timezone')]);

            $createTeam->handle($user, "{$user->name}'s Team", isPersonal: true);
            $user->switchTeam($team);

            $family = collect(['Jordan', 'Maya', 'Leo'])->mapWithKeys(function (string $name) use ($email, $team): array {
                $member = $this->member("{$name} Rivera", Str::replaceFirst('@', '+'.Str::lower($name).'@', $email), Str::password());
                $team->memberships()->create(['user_id' => $member->id, 'role' => TeamRole::Member]);
                $member->switchTeam($team);

                return [$name => $member];
            });

            $this->recipes($team, $createRecipe);
            $this->inventory($team, $createItem);
            $this->checklists($team, $user, $family->all());
            $this->routines($team, $family->all());

            return $team;
        });

        $this->completeTodaysSteps($team, $occurrences);

        $this->components->info("Created the demo household for {$email}.");

        return self::SUCCESS;
    }

    private function member(string $name, string $email, string $password): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function recipes(Team $team, CreateRecipe $createRecipe): void
    {
        $recipes = [
            [
                'name' => 'Lemon Garlic Chicken Thighs',
                'photo' => 'lemon-chicken.jpg',
                'tags' => ['Dinner', 'One Pan'],
                'servings' => '4',
                'prep_time' => '10 mins',
                'cook_time' => '35 mins',
                'total_time' => '45 mins',
                'description' => 'Crispy skillet chicken with lemon, garlic and rosemary. Our go-to busy weeknight dinner.',
                'ingredients' => ['8 bone-in chicken thighs', '1 lemon, sliced', '10 cloves garlic, smashed', '3 sprigs rosemary', '2 tbsp olive oil', '½ cup chicken broth', 'Salt and pepper'],
                'instructions' => ['Heat the oven to 425°F and season the chicken well.', 'Sear skin-side down in an oven-safe skillet until golden, about 8 minutes.', 'Flip, add the garlic, lemon, rosemary and broth.', 'Roast for 25 minutes, until the chicken reaches 175°F.', 'Spoon the pan juices over the top and serve.'],
            ],
            [
                'name' => 'Sheet Pan Gnocchi',
                'photo' => 'sheet-pan-gnocchi.jpg',
                'tags' => ['Dinner', 'Vegetarian'],
                'servings' => '4',
                'prep_time' => '10 mins',
                'cook_time' => '25 mins',
                'total_time' => '35 mins',
                'description' => 'Shelf-stable gnocchi roasted with summer vegetables until crisp.',
                'ingredients' => ['1 lb shelf-stable gnocchi', '1 pint cherry tomatoes', '1 zucchini, chopped', '1 red onion, cut into wedges', '3 tbsp olive oil', 'Fresh basil', 'Parmesan, to serve'],
                'instructions' => ['Heat the oven to 450°F.', 'Toss the gnocchi and vegetables with oil, salt and pepper on a sheet pan.', 'Roast for 20–25 minutes, stirring halfway.', 'Top with basil and parmesan.'],
            ],
            [
                'name' => 'Blueberry Lemon Pancakes',
                'photo' => 'blueberry-pancakes.jpg',
                'tags' => ['Breakfast'],
                'servings' => '12 pancakes',
                'prep_time' => '10 mins',
                'cook_time' => '15 mins',
                'total_time' => '25 mins',
                'description' => 'Saturday morning tradition. Maya does the blueberries.',
                'ingredients' => ['2 cups flour', '2 tbsp sugar', '2 tsp baking powder', '1¾ cups milk', '2 eggs', '3 tbsp melted butter', 'Zest of 1 lemon', '1 cup blueberries'],
                'instructions' => ['Whisk the dry ingredients together.', 'Whisk the milk, eggs, butter and zest, then stir into the dry ingredients.', 'Pour ¼ cup batter onto a hot griddle and scatter with blueberries.', 'Flip when bubbles form and cook until golden.'],
            ],
            [
                'name' => 'Weeknight Beef Tacos',
                'photo' => 'beef-tacos.jpg',
                'tags' => ['Dinner', 'Kid Favorite'],
                'servings' => '4',
                'prep_time' => '10 mins',
                'cook_time' => '15 mins',
                'total_time' => '25 mins',
                'description' => 'Taco Tuesday, every Tuesday.',
                'ingredients' => ['1 lb ground beef', '2 tbsp taco seasoning', '8 small tortillas', 'Shredded lettuce', 'Shredded cheddar', 'Pico de gallo', 'Lime wedges'],
                'instructions' => ['Brown the beef and drain.', 'Stir in the seasoning and ⅓ cup water and simmer until thick.', 'Warm the tortillas.', 'Fill and top however you like.'],
            ],
            [
                'name' => 'Tomato Basil Soup',
                'photo' => 'tomato-soup.jpg',
                'tags' => ['Lunch', 'Vegetarian'],
                'servings' => '6',
                'prep_time' => '10 mins',
                'cook_time' => '30 mins',
                'total_time' => '40 mins',
                'description' => 'Creamy, cozy and perfect with grilled cheese.',
                'ingredients' => ['2 tbsp butter', '1 onion, diced', '3 cloves garlic', '2 (28 oz) cans whole tomatoes', '2 cups vegetable broth', '½ cup cream', 'Handful of basil'],
                'instructions' => ['Soften the onion and garlic in butter.', 'Add the tomatoes and broth and simmer for 20 minutes.', 'Blend until smooth with the basil.', 'Stir in the cream and season to taste.'],
            ],
            [
                'name' => 'Chocolate Chip Cookies',
                'photo' => 'chocolate-chip-cookies.jpg',
                'tags' => ['Dessert'],
                'servings' => '24 cookies',
                'prep_time' => '15 mins',
                'cook_time' => '12 mins',
                'total_time' => '27 mins',
                'description' => 'Grandma Rivera\'s recipe. Chill the dough if you can wait.',
                'ingredients' => ['1 cup butter, softened', '¾ cup brown sugar', '¾ cup sugar', '2 eggs', '2 tsp vanilla', '2¼ cups flour', '1 tsp baking soda', '1 tsp salt', '2 cups chocolate chips'],
                'instructions' => ['Cream the butter and sugars.', 'Beat in the eggs and vanilla.', 'Mix in the dry ingredients, then the chocolate chips.', 'Scoop onto sheets and bake at 375°F for 10–12 minutes.'],
            ],
            [
                'name' => 'Veggie Fried Rice',
                'photo' => 'veggie-fried-rice.jpg',
                'tags' => ['Dinner', 'Vegetarian'],
                'servings' => '4',
                'prep_time' => '10 mins',
                'cook_time' => '10 mins',
                'total_time' => '20 mins',
                'description' => 'The best use of leftover rice.',
                'ingredients' => ['4 cups cold cooked rice', '1 cup peas and carrots', '3 eggs', '3 green onions', '3 tbsp soy sauce', '1 tbsp sesame oil'],
                'instructions' => ['Scramble the eggs in a hot wok and set aside.', 'Stir-fry the vegetables for 2 minutes.', 'Add the rice and fry until hot.', 'Toss in the eggs, soy sauce, sesame oil and green onions.'],
            ],
        ];

        foreach ($recipes as $recipe) {
            $photo = $recipe['photo'];

            $createRecipe->handle($team, [
                ...$recipe,
                'ingredients' => $this->list('ul', $recipe['ingredients']),
                'instructions' => $this->list('ol', $recipe['instructions']),
                'photo' => $this->photo("recipes/{$photo}"),
            ]);
        }
    }

    private function inventory(Team $team, CreateItem $createItem): void
    {
        $tree = [
            'Garage' => [
                'Tool Chest' => ['Cordless Drill' => ['photo' => 'cordless-drill.jpg', 'metadata' => ['Battery' => '20V', 'Bought' => '2024']], 'Stud Finder' => [], 'Socket Set' => []],
                'Camping Gear' => ['4-Person Tent' => ['photo' => 'camping-tent.jpg'], 'Sleeping Bags' => ['metadata' => ['Count' => '4']], 'Camp Stove' => []],
                'Holiday Decorations' => ['String Lights' => ['photo' => 'holiday-lights.jpg', 'metadata' => ['Length' => '50 ft']], 'Wreath' => [], 'Tree Topper' => []],
            ],
            'Kitchen' => [
                'Pantry' => ['Stand Mixer' => ['photo' => 'stand-mixer.jpg'], 'Waffle Iron' => [], 'Slow Cooker' => []],
                'Baking Cabinet' => ['Cookie Sheets' => [], 'Cooling Racks' => [], 'Muffin Tin' => []],
            ],
            'Hall Closet' => [
                'Board Games' => ['Ticket to Ride' => [], 'Catan' => [], 'Uno' => []],
                'First Aid Kit' => [],
            ],
            'Basement' => [
                'Kids\' Art Supplies' => ['Watercolors' => [], 'Sketchbooks' => []],
                'Spare Linens' => [],
            ],
        ];

        foreach ($tree as $location => $bins) {
            $parent = $createItem->handle($team, ['name' => $location, 'type' => ItemType::Location]);

            foreach ($bins as $bin => $items) {
                $container = $createItem->handle($team, ['name' => $bin, 'type' => ItemType::Bin, 'parent_id' => $parent->id]);

                foreach ($items as $name => $details) {
                    $this->item($team, $createItem, $container, $name, $details);
                }
            }
        }
    }

    /** @param array{photo?: string, metadata?: array<string, string>} $details */
    private function item(Team $team, CreateItem $createItem, Item $parent, string $name, array $details): void
    {
        $createItem->handle($team, [
            'name' => $name,
            'type' => ItemType::Item,
            'parent_id' => $parent->id,
            'metadata' => $details['metadata'] ?? null,
            'photo' => isset($details['photo']) ? $this->photo("items/{$details['photo']}") : null,
        ]);
    }

    /** @param array<string, User> $family */
    private function checklists(Team $team, User $user, array $family): void
    {
        $lists = [
            ['Groceries', ChecklistType::Shopping, null, ['Milk', 'Eggs', 'Blueberries', 'Chicken thighs', 'Lemons', 'Tortillas', 'Shredded cheddar', 'Basil', 'Coffee beans', 'Bananas'], 4],
            ['Costco Run', ChecklistType::Shopping, null, ['Paper towels', 'Dog food', 'Olive oil', 'Frozen berries'], 0],
            ['Weekend Projects', ChecklistType::Todo, $user, ['Replace the porch light', 'Clean out the gutters', 'Hang the family photos', 'Fix the squeaky door'], 1],
            ['Back to School', ChecklistType::Todo, null, ['Order new backpacks', 'Label water bottles', 'Sign permission slips'], 2],
            ['Maya\'s Birthday Wishlist', ChecklistType::Wishlist, $family['Maya'], ['Art easel', 'Roller skates', 'Mystery book series'], 0],
            ['Leo\'s Wishlist', ChecklistType::Wishlist, $family['Leo'], ['Building blocks set', 'Dinosaur pajamas', 'Soccer ball'], 0],
        ];

        foreach ($lists as [$name, $type, $owner, $items, $completed]) {
            $checklist = $team->checklists()->create(['name' => $name, 'type' => $type, 'user_id' => $owner?->id]);

            foreach ($items as $position => $item) {
                $created = $checklist->items()->create(['name' => $item, 'position' => $position]);

                if ($position < $completed) {
                    $created->complete($user);
                }
            }
        }
    }

    /** @param array<string, User> $family */
    private function routines(Team $team, array $family): void
    {
        $routines = [
            ['Morning Routine', $family['Maya'], TimeOfDay::Morning, ['frequency' => RoutineFrequency::Daily], ['Make bed', 'Brush teeth', 'Get dressed', 'Pack backpack']],
            ['Morning Routine', $family['Leo'], TimeOfDay::Morning, ['frequency' => RoutineFrequency::Daily], ['Make bed', 'Brush teeth', 'Feed the dog']],
            ['Feed Biscuit', null, TimeOfDay::Evening, ['frequency' => RoutineFrequency::Daily], ['Fill the food bowl', 'Fresh water', 'Evening walk']],
            ['Bedtime', null, TimeOfDay::Evening, ['frequency' => RoutineFrequency::Daily], ['Pick up toys', 'Bath', 'Read a story', 'Lights out']],
            ['Weekly Reset', null, TimeOfDay::Afternoon, ['frequency' => RoutineFrequency::Weekly, 'weekdays' => [Carbon::SUNDAY]], ['Plan meals', 'Update the grocery list', 'Take out recycling', 'Water the plants']],
            ['Pay Bills', $family['Jordan'], TimeOfDay::Anytime, ['frequency' => RoutineFrequency::Monthly, 'day_of_month' => 1], ['Mortgage', 'Utilities', 'Internet']],
        ];

        foreach ($routines as [$name, $owner, $timeOfDay, $schedule, $steps]) {
            $routine = $team->routines()->create([
                'name' => $name,
                'user_id' => $owner?->id,
                'time_of_day' => $timeOfDay,
                'starts_on' => $team->today()->subMonth(),
                ...$schedule,
            ]);

            $routine->steps()->createMany(
                collect($steps)->map(fn (string $step, int $position): array => ['name' => $step, 'position' => $position])->all(),
            );
        }
    }

    private function completeTodaysSteps(Team $team, GenerateRoutineOccurrences $occurrences): void
    {
        foreach ($occurrences->forDate($team) as $occurrence) {
            if ($occurrence->routine->time_of_day !== TimeOfDay::Morning) {
                continue;
            }

            $occurrence->steps
                ->take($occurrence->routine->user?->name === 'Leo Rivera' ? 2 : $occurrence->steps->count())
                ->each->complete($occurrence->routine->user);
        }
    }

    /** @param array<int, string> $items */
    private function list(string $tag, array $items): string
    {
        return "<{$tag}>".collect($items)->map(fn (string $item): string => '<li>'.e($item).'</li>')->implode('')."</{$tag}>";
    }

    private function photo(string $path): UploadedFile
    {
        return new UploadedFile(resource_path("demo/{$path}"), basename($path), 'image/jpeg', null, true);
    }
}
