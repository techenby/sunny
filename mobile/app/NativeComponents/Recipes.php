<?php

namespace App\NativeComponents;

use App\Icons\Android;
use App\Icons\Ios;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class Recipes extends NativeComponent
{
    /** @var array<string, array{ios: Ios, android: Android}> */
    private const COURSE_ICONS = [
        'Breakfast' => ['ios' => Ios::CupAndSaucer, 'android' => Android::BreakfastDining],
        'Dinner' => ['ios' => Ios::ForkKnife, 'android' => Android::DinnerDining],
        'Dessert' => ['ios' => Ios::BirthdayCake, 'android' => Android::Cake],
    ];

    /**
     * Placeholder recipes until the app syncs with sunnyhome.app.
     *
     * @return list<array{id: int, name: string, course: string, servings: int, prepMinutes: int, cookMinutes: int, ingredients: list<string>, steps: list<string>}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Buttermilk Pancakes',
                'course' => 'Breakfast',
                'servings' => 4,
                'prepMinutes' => 10,
                'cookMinutes' => 15,
                'ingredients' => ['2 cups flour', '2 cups buttermilk', '2 eggs', '3 tbsp melted butter', '2 tbsp sugar', '2 tsp baking powder'],
                'steps' => [
                    'Whisk the dry ingredients together in a large bowl.',
                    'Stir in the buttermilk, eggs, and melted butter until just combined.',
                    'Cook ¼ cup of batter at a time on a hot griddle until bubbles form, then flip.',
                ],
            ],
            [
                'id' => 2,
                'name' => 'Veggie Frittata',
                'course' => 'Breakfast',
                'servings' => 6,
                'prepMinutes' => 10,
                'cookMinutes' => 25,
                'ingredients' => ['8 eggs', '½ cup milk', '1 bell pepper', '2 cups spinach', '½ cup feta'],
                'steps' => [
                    'Heat the oven to 375°F.',
                    'Sauté the pepper and spinach in an oven-safe skillet.',
                    'Pour in the whisked eggs and milk, top with feta, and bake until set.',
                ],
            ],
            [
                'id' => 3,
                'name' => 'Overnight Oats',
                'course' => 'Breakfast',
                'servings' => 2,
                'prepMinutes' => 10,
                'cookMinutes' => 0,
                'ingredients' => ['1 cup rolled oats', '1 cup milk', '½ cup Greek yogurt', '1 tbsp chia seeds', '1 tbsp maple syrup'],
                'steps' => [
                    'Stir everything together in a jar.',
                    'Cover and refrigerate overnight.',
                    'Top with fruit before serving.',
                ],
            ],
            [
                'id' => 4,
                'name' => 'Weeknight Chili',
                'course' => 'Dinner',
                'servings' => 6,
                'prepMinutes' => 15,
                'cookMinutes' => 30,
                'ingredients' => ['1 lb ground beef', '1 onion', '2 cans kidney beans', '1 can crushed tomatoes', '2 tbsp chili powder', '1 tsp cumin'],
                'steps' => [
                    'Brown the beef with the diced onion.',
                    'Add the spices and cook for one minute.',
                    'Stir in the beans and tomatoes, then simmer for 25 minutes.',
                ],
            ],
            [
                'id' => 5,
                'name' => 'Lemon Garlic Chicken',
                'course' => 'Dinner',
                'servings' => 4,
                'prepMinutes' => 10,
                'cookMinutes' => 30,
                'ingredients' => ['4 chicken thighs', '1 lemon', '4 garlic cloves', '2 tbsp olive oil', '1 tsp dried oregano'],
                'steps' => [
                    'Heat the oven to 425°F.',
                    'Toss the chicken with oil, lemon juice, garlic, and oregano.',
                    'Roast until golden and cooked through.',
                ],
            ],
            [
                'id' => 6,
                'name' => 'Sheet Pan Gnocchi',
                'course' => 'Dinner',
                'servings' => 4,
                'prepMinutes' => 5,
                'cookMinutes' => 25,
                'ingredients' => ['1 lb shelf-stable gnocchi', '1 pint cherry tomatoes', '1 zucchini', '2 tbsp olive oil', 'Fresh basil'],
                'steps' => [
                    'Heat the oven to 450°F.',
                    'Toss the gnocchi and vegetables with oil on a sheet pan.',
                    'Roast for 25 minutes, then top with basil.',
                ],
            ],
            [
                'id' => 7,
                'name' => 'Grandma’s Lasagna',
                'course' => 'Dinner',
                'servings' => 8,
                'prepMinutes' => 30,
                'cookMinutes' => 60,
                'ingredients' => ['12 lasagna noodles', '1 lb Italian sausage', '3 cups marinara', '15 oz ricotta', '3 cups mozzarella', '1 egg'],
                'steps' => [
                    'Brown the sausage and stir in the marinara.',
                    'Mix the ricotta with the egg.',
                    'Layer noodles, ricotta, sauce, and mozzarella three times.',
                    'Cover and bake at 375°F for 45 minutes, then uncover for 15 more.',
                ],
            ],
            [
                'id' => 8,
                'name' => 'Chocolate Chip Cookies',
                'course' => 'Dessert',
                'servings' => 24,
                'prepMinutes' => 15,
                'cookMinutes' => 15,
                'ingredients' => ['2¼ cups flour', '1 cup butter', '¾ cup brown sugar', '¾ cup sugar', '2 eggs', '2 cups chocolate chips'],
                'steps' => [
                    'Cream the butter and sugars, then beat in the eggs.',
                    'Mix in the flour, then fold in the chocolate chips.',
                    'Bake spoonfuls at 375°F for 10 minutes.',
                ],
            ],
            [
                'id' => 9,
                'name' => 'Apple Crisp',
                'course' => 'Dessert',
                'servings' => 8,
                'prepMinutes' => 15,
                'cookMinutes' => 40,
                'ingredients' => ['6 apples', '1 cup rolled oats', '½ cup flour', '½ cup brown sugar', '½ cup butter', '1 tsp cinnamon'],
                'steps' => [
                    'Slice the apples into a baking dish.',
                    'Rub the oats, flour, sugar, butter, and cinnamon into a crumble.',
                    'Scatter over the apples and bake at 350°F until bubbling.',
                ],
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, course: string, servings: int, prepMinutes: int, cookMinutes: int, ingredients: list<string>, steps: list<string>}|null
     */
    public static function find(int $id): ?array
    {
        return collect(static::all())->firstWhere('id', $id);
    }

    /**
     * @return list<array{course: string, ios: Ios, android: Android, recipes: list<array{id: int, name: string, servings: int, minutes: int}>}>
     */
    #[Computed(persist: true)]
    public function courses(): array
    {
        return collect(static::all())
            ->groupBy('course')
            ->map(fn ($recipes, string $course): array => [
                'course' => $course,
                ...self::COURSE_ICONS[$course],
                'recipes' => $recipes->map(fn (array $recipe): array => [
                    'id' => $recipe['id'],
                    'name' => $recipe['name'],
                    'servings' => $recipe['servings'],
                    'minutes' => $recipe['prepMinutes'] + $recipe['cookMinutes'],
                ])->all(),
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('native.recipes');
    }
}
