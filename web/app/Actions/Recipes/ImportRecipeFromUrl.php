<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ImportRecipeFromUrl
{
    public function handle(string $url): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; RecipeImporter/1.0)',
        ])->get($url);

        throw_unless($response->successful(), RuntimeException::class, 'Failed to fetch the URL. Please check the URL and try again.');

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
            'name' => $recipe['name'] ?? null,
            'source' => $recipe['url'] ?? $url,
            'tags' => $this->extractTags($recipe),
            'servings' => isset($recipe['recipeYield']) ? $this->normalizeYield($recipe['recipeYield']) : null,
            'prep_time' => isset($recipe['prepTime']) ? $this->formatDuration($recipe['prepTime']) : null,
            'cook_time' => isset($recipe['cookTime']) ? $this->formatDuration($recipe['cookTime']) : null,
            'total_time' => isset($recipe['totalTime']) ? $this->formatDuration($recipe['totalTime']) : null,
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

    private function normalizeYield(mixed $yield): string
    {
        if (is_array($yield)) {
            return (string) ($yield[0] ?? '');
        }

        return (string) $yield;
    }

    private function formatIngredients(array $ingredients): string
    {
        $items = array_map(fn (string $item) => '<li>' . e(trim($item)) . '</li>', $ingredients);

        return '<ul>' . implode('', $items) . '</ul>';
    }

    private function formatInstructions(array $instructions): string
    {
        $items = [];

        foreach ($instructions as $instruction) {
            if (is_string($instruction)) {
                $items[] = '<li>' . e(trim($instruction)) . '</li>';
            } elseif (is_array($instruction)) {
                $type = $instruction['@type'] ?? '';

                if ($type === 'HowToSection') {
                    $sectionName = $instruction['name'] ?? '';
                    $steps = $instruction['itemListElement'] ?? [];

                    if ($sectionName !== '') {
                        $items[] = '<li><strong>' . e(trim($sectionName)) . '</strong>';
                    }

                    if (is_array($steps) && $steps !== []) {
                        $items[] = '<ol>' . implode('', array_map(function ($step) {
                            $text = is_array($step) ? ($step['text'] ?? $step['name'] ?? '') : (string) $step;

                            return '<li>' . e(trim((string) $text)) . '</li>';
                        }, $steps)) . '</ol>';
                    }

                    if ($sectionName !== '') {
                        $items[] = '</li>';
                    }
                } else {
                    $text = $instruction['text'] ?? $instruction['name'] ?? '';
                    $items[] = '<li>' . e(trim((string) $text)) . '</li>';
                }
            }
        }

        return '<ol>' . implode('', $items) . '</ol>';
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
