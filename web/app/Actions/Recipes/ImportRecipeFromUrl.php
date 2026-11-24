<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Support\PublicHttp;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Throwable;

class ImportRecipeFromUrl
{
    public function handle(string $url): array
    {
        try {
            $response = resolve(PublicHttp::class)->get($url, [
                'User-Agent' => 'Mozilla/5.0 (compatible; RecipeImporter/1.0)',
            ]);
        } catch (ConnectionException $exception) {
            throw new RecipePageUnavailable('Failed to fetch the URL. Please check the URL and try again.', previous: $exception);
        }

        throw_unless($response->successful(), RecipePageUnavailable::class, 'Failed to fetch the URL. Please check the URL and try again.');

        $recipe = $this->extractRecipeSchema($response->body());

        throw_unless($recipe, RuntimeException::class, 'No recipe data found on this page.');

        return $this->mapToFormFields($recipe, $url);
    }

    /**
     * Convert an ISO 8601 duration (e.g., PT1H30M) to a human-readable string.
     */
    public function formatDuration(string $duration): string
    {
        try {
            return CarbonInterval::make($duration)->forHumans(['short' => true]);
        } catch (Throwable) {
            return $duration;
        }
    }

    /** @return array<string, mixed>|null */
    private function extractRecipeSchema(string $html): ?array
    {
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

        return collect($matches[1])
            ->map(fn (string $json) => json_decode(trim($json), true))
            ->filter(fn ($item) => is_array($item)) // filters out null
            ->map(fn (array $data) => $this->findRecipeInData($data))
            ->first(fn ($recipe) => $recipe !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function findRecipeInData(array $data): ?array
    {
        $type = $data['@type'] ?? null;

        if ($type === 'Recipe' || (is_array($type) && in_array('Recipe', $type))) {
            return $data;
        }

        $children = $data['@graph'] ?? (array_is_list($data) ? $data : []);

        return collect($children)
            ->filter(fn ($item) => is_array($item)) // guards againts type error
            ->map(fn (array $item) => $this->findRecipeInData($item))
            ->first(fn ($recipe) => $recipe !== null);
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return array<string, mixed>
     */
    private function mapToFormFields(array $recipe, string $url): array
    {
        return [
            'name' => $this->limit($recipe['name'] ?? null, 255),
            'source' => collect([$recipe['url'] ?? null, $url])->first(fn (mixed $source): bool => is_string($source) && mb_strlen($source) <= 500),
            'tags' => $this->extractTags($recipe),
            'servings' => isset($recipe['recipeYield']) ? $this->limit($this->normalizeYield($recipe['recipeYield']), 50) : null,
            'prep_time' => isset($recipe['prepTime']) ? $this->limit($this->formatDuration($recipe['prepTime']), 50) : null,
            'cook_time' => isset($recipe['cookTime']) ? $this->limit($this->formatDuration($recipe['cookTime']), 50) : null,
            'total_time' => isset($recipe['totalTime']) ? $this->limit($this->formatDuration($recipe['totalTime']), 50) : null,
            'description' => isset($recipe['description']) ? strip_tags((string) $recipe['description']) : null,
            'ingredients' => isset($recipe['recipeIngredient']) ? $this->formatIngredients($recipe['recipeIngredient']) : null,
            'instructions' => isset($recipe['recipeInstructions']) ? $this->formatInstructions($recipe['recipeInstructions']) : null,
            'nutrition' => isset($recipe['nutrition']) ? $this->formatNutrition($recipe['nutrition']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return string[]
     */
    private function extractTags(array $recipe): array
    {
        $knownTags = [
            'Breakfast', 'Lunch', 'Dinner', 'Dessert', 'Meal',
            'Gluten Free', 'Dairy Free', 'Diabetes-Friendly',
            'Vegetarian', 'Vegan', 'Overnight', 'Slow Cooker',
        ];

        $candidates = collect();

        if (isset($recipe['recipeCategory'])) {
            $candidates = $candidates->merge((array) $recipe['recipeCategory']);
        }

        if (isset($recipe['keywords'])) {
            $keywords = is_string($recipe['keywords'])
                ? explode(',', $recipe['keywords'])
                : (array) $recipe['keywords'];
            $candidates = $candidates->merge($keywords);
        }

        $knownTagsLower = collect($knownTags)->mapWithKeys(fn (string $tag) => [mb_strtolower($tag) => $tag]);

        return $candidates
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->map(fn (string $candidate) => $knownTagsLower->get(mb_strtolower($candidate)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function limit(mixed $value, int $length): ?string
    {
        return is_scalar($value) ? trim(mb_substr(trim((string) $value), 0, $length)) : null;
    }

    private function normalizeYield(mixed $yield): string
    {
        if (is_array($yield)) {
            return (string) ($yield[0] ?? '');
        }

        return (string) $yield;
    }

    private function formatIngredients(array $ingredients): string
    {
        return collect($ingredients)
            ->map(fn (mixed $item): string => $this->clean($item))
            ->filter()
            ->implode("\n");
    }

    private function formatInstructions(array $instructions): string
    {
        $lines = [];

        foreach ($instructions as $instruction) {
            if (! is_array($instruction)) {
                $lines[] = $this->clean($instruction);

                continue;
            }

            if (($instruction['@type'] ?? '') !== 'HowToSection') {
                $lines[] = $this->clean($instruction['text'] ?? $instruction['name'] ?? '');

                continue;
            }

            $section = $this->clean($instruction['name'] ?? '');
            $lines[] = $section === '' ? '' : "# {$section}";

            foreach ((array) ($instruction['itemListElement'] ?? []) as $step) {
                $lines[] = $this->clean(is_array($step) ? ($step['text'] ?? $step['name'] ?? '') : $step);
            }
        }

        return implode("\n", array_filter($lines));
    }

    private function clean(mixed $text): string
    {
        if (! is_scalar($text)) {
            return '';
        }

        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? '');
    }

    /** @param  array<string, mixed>  $nutrition */
    private function formatNutrition(array $nutrition): string
    {
        $labels = [
            'calories' => 'Calories',
            'fatContent' => 'Fat',
            'saturatedFatContent' => 'Saturated Fat',
            'unsaturatedFatContent' => 'Unsaturated Fat',
            'transFatContent' => 'Trans Fat',
            'carbohydrateContent' => 'Carbohydrates',
            'sugarContent' => 'Sugar',
            'fiberContent' => 'Fiber',
            'proteinContent' => 'Protein',
            'cholesterolContent' => 'Cholesterol',
            'sodiumContent' => 'Sodium',
            'servingSize' => 'Serving Size',
        ];

        $parts = [];

        foreach ($labels as $key => $label) {
            if (isset($nutrition[$key]) && $nutrition[$key] !== '') {
                $parts[] = "{$label}: {$nutrition[$key]}";
            }
        }

        return implode("\n", $parts);
    }
}
