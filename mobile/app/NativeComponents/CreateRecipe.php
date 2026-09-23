<?php

namespace App\NativeComponents;

use App\Concerns\CapturesPhoto;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class CreateRecipe extends NativeComponent
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

        foreach (self::TAG_OPTIONS as $tag) {
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
     * becomes a list item — the shape RecipeDetail::lines() reads back out.
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

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        // The cookbook is still the hardcoded placeholder list in
        // Recipes::all(), so there is nowhere to write to yet — return to the
        // list once the form is valid, until the sunnyhome.app API is wired up.
        $this->back();
    }

    public function render(): View
    {
        return view('native.create-recipe');
    }

    /**
     * Mirrors the web form's rules: a required name, plus the column limits
     * that would otherwise only fail once the API rejected the request.
     */
    private function validationError(): string
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
