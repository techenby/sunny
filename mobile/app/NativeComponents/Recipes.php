<?php

namespace App\NativeComponents;

use App\Models\Recipe;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class Recipes extends NativeComponent
{
    /**
     * The last downloaded recipes, read entirely from the local database.
     *
     * @return list<array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: string|null, instructions: string|null, notes: string|null, nutrition: string|null, tags: list<string>|null, created_at: string, updated_at: string}>
     */
    public static function all(): array
    {
        return Recipe::forActiveTeam()->orderBy('id')->get()->toArray();
    }

    /**
     * @return array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: string|null, instructions: string|null, notes: string|null, nutrition: string|null, tags: list<string>|null, created_at: string, updated_at: string}|null
     */
    public static function find(int $id): ?array
    {
        return Recipe::forActiveTeam()->find($id)?->toArray();
    }

    /**
     * The most recently added or updated recipes, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 3): array
    {
        return Recipe::forActiveTeam()->orderByDesc('updated_at')->limit($limit)->get()->toArray();
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
    #[Computed]
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
