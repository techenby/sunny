<?php

namespace App\Concerns;

use App\NativeComponents\Recipes;
use Native\Mobile\Attributes\Computed;

/**
 * The recipe form's state and rules, shared by the create and edit screens.
 *
 * Mirrors App\Livewire\Forms\Recipes\RecipeForm in the sunnyhome.app web app,
 * minus parent_id — the web form doesn't expose it either, since the remix
 * action is what sets it.
 */
trait ManagesRecipeForm
{
    use CapturesPhoto;

    /**
     * The tag vocabulary the web app's recipe form offers.
     *
     * @var list<string>
     */
    public const TAG_OPTIONS = [
        'Breakfast',
        'Lunch',
        'Dinner',
        'Dessert',
        'Meal',
        'Gluten Free',
        'Dairy Free',
        'Diabetes-Friendly',
        'Vegetarian',
        'Vegan',
        'Overnight',
        'Slow Cooker',
    ];

    public string $name = '';

    public string $source = '';

    /** @var list<string> */
    public array $tags = [];

    public string $servings = '';

    public string $prepTime = '';

    public string $cookTime = '';

    public string $totalTime = '';

    public string $description = '';

    public string $ingredients = '';

    public string $instructions = '';

    public string $notes = '';

    public string $nutrition = '';

    public string $error = '';

    /**
     * Load an existing recipe into the form, turning the stored rich-text
     * HTML back into the one-per-line text the fields edit.
     *
     * @param  array<string, mixed>  $recipe
     */
    public function fillFromRecipe(array $recipe): void
    {
        $this->name = (string) ($recipe['name'] ?? '');
        $this->source = (string) ($recipe['source'] ?? '');
        $this->tags = array_values($recipe['tags'] ?? []);
        $this->servings = (string) ($recipe['servings'] ?? '');
        $this->prepTime = (string) ($recipe['prep_time'] ?? '');
        $this->cookTime = (string) ($recipe['cook_time'] ?? '');
        $this->totalTime = (string) ($recipe['total_time'] ?? '');
        $this->description = (string) ($recipe['description'] ?? '');
        $this->ingredients = implode("\n", Recipes::lines($recipe['ingredients'] ?? null));
        $this->instructions = implode("\n", Recipes::lines($recipe['instructions'] ?? null));
        $this->notes = (string) ($recipe['notes'] ?? '');
        $this->nutrition = (string) ($recipe['nutrition'] ?? '');
    }

    /**
     * The chip vocabulary, plus any tag the recipe already carries that is not
     * in it. Without the union an existing tag outside the list would vanish
     * the first time the recipe was edited.
     *
     * Matching is case-insensitive, and a tag the recipe already has keeps the
     * recipe's own casing: the stored tags are lowercase while the vocabulary
     * is title case, so comparing exactly would show "breakfast" and
     * "Breakfast" as two separate chips that collide on the same ref.
     *
     * @return list<string>
     */
    #[Computed]
    public function tagOptions(): array
    {
        $existing = collect($this->tags)->keyBy(fn (string $tag): string => mb_strtolower($tag));

        $vocabulary = collect(self::TAG_OPTIONS)
            ->map(fn (string $tag): string => $existing->get(mb_strtolower($tag), $tag));

        return $vocabulary
            ->merge($existing->values())
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->values()
            ->all();
    }

    /**
     * The tags packed into rows that fit the screen width.
     *
     * The renderer's flex container accepts `flex_wrap` but never acts on it,
     * so a single row of chips is squeezed to a fraction of each label's width
     * instead of wrapping. Greedy-packing here keeps every chip at its natural
     * size. The budget is in characters — roughly a 320pt-wide screen, with
     * each chip costing its label plus padding and the gap that follows it.
     *
     * @return list<list<string>>
     */
    #[Computed]
    public function tagRows(): array
    {
        $budget = 42;
        $rows = [];
        $row = [];
        $used = 0;

        foreach ($this->tagOptions as $tag) {
            $cost = mb_strlen($tag) + 5;

            if ($row !== [] && $used + $cost > $budget) {
                $rows[] = $row;
                $row = [];
                $used = 0;
            }

            $row[] = $tag;
            $used += $cost;
        }

        if ($row !== []) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function toggleTag(string $tag, bool $selected): void
    {
        $tags = collect($this->tags)->reject(fn (string $existing): bool => $existing === $tag);

        if ($selected) {
            $tags->push($tag);
        }

        $this->tags = $tags->values()->all();
    }

    /**
     * Ingredients are stored as the HTML the web app's rich-text editor
     * produces. They are typed one per line here, so each non-empty line
     * becomes a list item — the shape Recipes::lines() reads back out.
     */
    #[Computed]
    public function ingredientsHtml(): ?string
    {
        return $this->htmlList($this->ingredients, 'ul');
    }

    /** @see ingredientsHtml() */
    #[Computed]
    public function instructionsHtml(): ?string
    {
        return $this->htmlList($this->instructions, 'ol');
    }

    /**
     * Mirrors the web form's rules: a required name, plus the column limits
     * that would otherwise only fail once the API rejected the request.
     */
    protected function validationError(): string
    {
        if (trim($this->name) === '') {
            return 'Give the recipe a name.';
        }

        $limits = [
            'The name' => [$this->name, 255],
            'The source' => [$this->source, 500],
            'Servings' => [$this->servings, 50],
            'Prep time' => [$this->prepTime, 50],
            'Cook time' => [$this->cookTime, 50],
            'Total time' => [$this->totalTime, 50],
        ];

        foreach ($limits as $label => [$value, $max]) {
            if (mb_strlen(trim($value)) > $max) {
                return "{$label} is too long ({$max} characters max).";
            }
        }

        return '';
    }

    /**
     * Wrap each non-empty line of $text as a list item, or null when blank.
     */
    private function htmlList(string $text, string $tag): ?string
    {
        $items = collect(preg_split('/\R/', $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(fn (string $line): string => '<li>'.e($line).'</li>');

        return $items->isEmpty() ? null : "<{$tag}>".$items->implode('')."</{$tag}>";
    }
}
