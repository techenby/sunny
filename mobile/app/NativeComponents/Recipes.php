<?php

namespace App\NativeComponents;

use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class Recipes extends NativeComponent
{
    /**
     * Placeholder recipes shaped like the sunnyhome.app Recipe model, until the app syncs with its API.
     *
     * @return list<array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: string|null, instructions: string|null, notes: string|null, nutrition: string|null, tags: list<string>|null, created_at: string, updated_at: string}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 1,
                'parent_id' => null,
                'name' => 'Buttermilk Pancakes',
                'source' => 'https://www.allrecipes.com/recipe/buttermilk-pancakes/',
                'servings' => '4',
                'prep_time' => '10 minutes',
                'cook_time' => '15 minutes',
                'total_time' => '25 minutes',
                'description' => 'Fluffy weekend pancakes that come together in one bowl.',
                'ingredients' => '<ul><li>2 cups flour</li><li>2 cups buttermilk</li><li>2 eggs</li><li>3 tbsp melted butter</li><li>2 tbsp sugar</li><li>2 tsp baking powder</li></ul>',
                'instructions' => '<ol><li>Whisk the dry ingredients together in a large bowl.</li><li>Stir in the buttermilk, eggs, and melted butter until just combined.</li><li>Cook ¼ cup of batter at a time on a hot griddle until bubbles form, then flip.</li></ol>',
                'notes' => 'Let the batter rest for five minutes for taller pancakes.',
                'nutrition' => null,
                'tags' => ['breakfast', 'weekend'],
                'created_at' => '2026-06-02 09:15:00',
                'updated_at' => '2026-09-21 08:30:00',
            ],
            [
                'id' => 2,
                'parent_id' => null,
                'name' => 'Weeknight Chili',
                'source' => 'https://www.budgetbytes.com/weeknight-chili/',
                'servings' => '6',
                'prep_time' => '15 minutes',
                'cook_time' => '30 minutes',
                'total_time' => '45 minutes',
                'description' => 'A hearty, freezer-friendly chili.',
                'ingredients' => '<ul><li>1 lb ground beef</li><li>1 onion, diced</li><li>2 cans kidney beans</li><li>1 can crushed tomatoes</li><li>2 tbsp chili powder</li><li>1 tsp cumin</li></ul>',
                'instructions' => '<ol><li>Brown the beef with the onion.</li><li>Add the spices and cook for one minute.</li><li>Stir in the beans and tomatoes, then simmer for 25 minutes.</li></ol>',
                'notes' => null,
                'nutrition' => "Calories: 410\nProtein: 28 g",
                'tags' => ['dinner', 'freezer-friendly'],
                'created_at' => '2026-07-11 18:05:00',
                'updated_at' => '2026-07-11 18:05:00',
            ],
            [
                'id' => 3,
                'parent_id' => null,
                'name' => 'Grandma’s Lasagna',
                'source' => 'Grandma’s recipe card',
                'servings' => '8',
                'prep_time' => '30 minutes',
                'cook_time' => '1 hour',
                'total_time' => '1 hour 30 minutes',
                'description' => 'The family favorite for birthdays and holidays.',
                'ingredients' => '<ul><li>12 lasagna noodles</li><li>1 lb Italian sausage</li><li>3 cups marinara</li><li>15 oz ricotta</li><li>3 cups mozzarella</li><li>1 egg</li></ul>',
                'instructions' => '<ol><li>Brown the sausage and stir in the marinara.</li><li>Mix the ricotta with the egg.</li><li>Layer noodles, ricotta, sauce, and mozzarella three times.</li><li>Cover and bake at 375°F for 45 minutes, then uncover for 15 more.</li></ol>',
                'notes' => 'Freezes well before baking.',
                'nutrition' => null,
                'tags' => ['dinner', 'family favorite'],
                'created_at' => '2026-05-19 17:40:00',
                'updated_at' => '2026-08-30 19:10:00',
            ],
            [
                'id' => 4,
                'parent_id' => 3,
                'name' => 'Veggie Lasagna',
                'source' => 'Grandma’s recipe card',
                'servings' => '8',
                'prep_time' => '30 minutes',
                'cook_time' => '1 hour',
                'total_time' => '1 hour 30 minutes',
                'description' => 'Grandma’s lasagna with roasted vegetables instead of sausage.',
                'ingredients' => '<ul><li>12 lasagna noodles</li><li>2 zucchini, sliced</li><li>1 eggplant, sliced</li><li>3 cups marinara</li><li>15 oz ricotta</li><li>3 cups mozzarella</li></ul>',
                'instructions' => '<ol><li>Roast the zucchini and eggplant at 425°F for 20 minutes.</li><li>Layer noodles, ricotta, vegetables, sauce, and mozzarella three times.</li><li>Cover and bake at 375°F for 45 minutes, then uncover for 15 more.</li></ol>',
                'notes' => null,
                'nutrition' => null,
                'tags' => ['dinner', 'vegetarian'],
                'created_at' => '2026-08-04 12:20:00',
                'updated_at' => '2026-08-04 12:20:00',
            ],
            [
                'id' => 5,
                'parent_id' => null,
                'name' => 'Overnight Oats',
                'source' => null,
                'servings' => '2',
                'prep_time' => '10 minutes',
                'cook_time' => null,
                'total_time' => '8 hours',
                'description' => null,
                'ingredients' => '<p>1 cup rolled oats</p><p>1 cup milk</p><p>½ cup Greek yogurt</p><p>1 tbsp chia seeds</p>',
                'instructions' => '<p>Stir everything together in a jar, cover, and refrigerate overnight.</p>',
                'notes' => null,
                'nutrition' => null,
                'tags' => null,
                'created_at' => '2026-09-18 21:00:00',
                'updated_at' => '2026-09-18 21:00:00',
            ],
            [
                'id' => 6,
                'parent_id' => null,
                'name' => 'Chocolate Chip Cookies',
                'source' => 'https://www.kingarthurbaking.com/recipes/chocolate-chip-cookies-recipe',
                'servings' => '24 cookies',
                'prep_time' => '15 minutes',
                'cook_time' => '10 minutes',
                'total_time' => '25 minutes',
                'description' => 'Crisp edges, chewy centers.',
                'ingredients' => '<ul><li>2¼ cups flour</li><li>1 cup butter</li><li>¾ cup brown sugar</li><li>¾ cup sugar</li><li>2 eggs</li><li>2 cups chocolate chips</li></ul>',
                'instructions' => '<ol><li>Cream the butter and sugars, then beat in the eggs.</li><li>Mix in the flour, then fold in the chocolate chips.</li><li>Bake spoonfuls at 375°F for 10 minutes.</li></ol>',
                'notes' => null,
                'nutrition' => null,
                'tags' => ['dessert'],
                'created_at' => '2026-04-27 15:45:00',
                'updated_at' => '2026-06-14 16:00:00',
            ],
        ];
    }

    /**
     * @return array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: string|null, instructions: string|null, notes: string|null, nutrition: string|null, tags: list<string>|null, created_at: string, updated_at: string}|null
     */
    public static function find(int $id): ?array
    {
        return collect(static::all())->firstWhere('id', $id);
    }

    /**
     * The most recently added or updated recipes, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 3): array
    {
        return collect(static::all())
            ->sortByDesc('updated_at')
            ->take($limit)
            ->values()
            ->all();
    }

    public static function isSourceUrl(?string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Mirrors Recipe::shortenedSource() in the web app: a URL's host without "www.", or plain text limited to 30 characters.
     */
    public static function shortenedSource(?string $source): ?string
    {
        if (blank($source)) {
            return null;
        }

        if (static::isSourceUrl($source)) {
            return str_replace('www.', '', parse_url($source, PHP_URL_HOST) ?? $source);
        }

        return Str::limit($source, 30);
    }

    /**
     * Split the rich-text editor's HTML into plain-text rows: one per list
     * item, or per paragraph/line when there is no list. The inverse of the
     * serialization the create and edit forms perform.
     *
     * @return list<string>
     */
    public static function lines(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        $blocks = preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $html, $matches)
            ? $matches[1]
            : preg_split('/<\/p>|<br\s*\/?>|\R/i', $html);

        return collect($blocks)
            ->map(fn (string $block): string => trim(html_entity_decode(strip_tags($block), ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, summary: string|null}>
     */
    #[Computed(persist: true)]
    public function recipes(): array
    {
        return collect(static::all())
            ->sortBy('name')
            ->map(fn (array $recipe): array => [
                'id' => $recipe['id'],
                'name' => $recipe['name'],
                'summary' => collect([static::shortenedSource($recipe['source']), $recipe['total_time']])->filter()->implode(' · ') ?: null,
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('native.recipes');
    }
}
