<?php

namespace App\Concerns;

use App\Casts\RecipeLines;
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
    use SavesSunnyRecord;

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

    /** @var array{ingredients: string, instructions: string}|null */
    public ?array $loadedLines = null;

    /**
     * Load an existing recipe into the form, turning the stored rows back
     * into the one-per-line text the fields edit.
     *
     * @param  array<string, mixed>  $recipe
     */
    public function fillFromRecipe(array $recipe): void
    {
        $this->initializeTeam($recipe['team_id']);
        $this->existingPhotoUrl = $recipe['photo_url'] ?? null;
        $this->name = (string) ($recipe['name'] ?? '');
        $this->source = (string) ($recipe['source'] ?? '');
        $this->tags = array_values($recipe['tags'] ?? []);
        $this->servings = (string) ($recipe['servings'] ?? '');
        $this->prepTime = (string) ($recipe['prep_time'] ?? '');
        $this->cookTime = (string) ($recipe['cook_time'] ?? '');
        $this->totalTime = (string) ($recipe['total_time'] ?? '');
        $this->description = (string) ($recipe['description'] ?? '');
        $this->ingredients = RecipeLines::toText($recipe['ingredients'] ?? null);
        $this->instructions = RecipeLines::toText($recipe['instructions'] ?? null);
        $this->notes = (string) ($recipe['notes'] ?? '');
        $this->nutrition = (string) ($recipe['nutrition'] ?? '');
        $this->loadedLines = ['ingredients' => $this->ingredients, 'instructions' => $this->instructions];
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

    protected function recipePayload(): array
    {
        $payload = [
            'name' => trim($this->name), 'source' => $this->source ?: null,
            'tags' => $this->tags, 'servings' => $this->servings ?: null,
            'prep_time' => $this->prepTime ?: null, 'cook_time' => $this->cookTime ?: null,
            'total_time' => $this->totalTime ?: null, 'description' => $this->description ?: null,
            'ingredients' => trim($this->ingredients) ?: null, 'instructions' => trim($this->instructions) ?: null,
            'notes' => $this->notes ?: null, 'nutrition' => $this->nutrition ?: null,
        ];
        if ($this->loadedLines !== null) {
            $payload['remove_photo'] = $this->photoRemoved;
            foreach (['ingredients', 'instructions'] as $field) {
                if ($this->{$field} === $this->loadedLines[$field]) {
                    unset($payload[$field]);
                }
            }
        }

        return $payload;
    }

    /**
     * @return array{url: string, host: string, path: string, initial: string}|null
     */
    protected function sourceLink(): ?array
    {
        $url = trim($this->source);

        if (mb_strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(mb_strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }

        $host = (string) preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        return [
            'url' => $url,
            'host' => $host,
            'path' => rtrim((string) parse_url($url, PHP_URL_PATH), '/'),
            'initial' => mb_strtoupper(mb_substr($host, 0, 1)),
        ];
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
            'The name is' => [$this->name, 255],
            'The source is' => [$this->source, 500],
            'Servings is' => [$this->servings, 50],
            'Prep time is' => [$this->prepTime, 50],
            'Cook time is' => [$this->cookTime, 50],
            'Total time is' => [$this->totalTime, 50],
            'The description is' => [$this->description, 20_000],
            'The ingredients are' => [$this->ingredients, 20_000],
            'The instructions are' => [$this->instructions, 20_000],
            'The notes are' => [$this->notes, 20_000],
            'The nutrition is' => [$this->nutrition, 5_000],
        ];

        foreach ($limits as $label => [$value, $max]) {
            if (mb_strlen(trim($value)) > $max) {
                return "{$label} too long (".number_format($max).' characters max).';
            }
        }

        return '';
    }
}
