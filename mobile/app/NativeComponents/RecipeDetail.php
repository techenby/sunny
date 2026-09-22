<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

class RecipeDetail extends NativeComponent
{
    /**
     * @return array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: string|null, instructions: string|null, notes: string|null, nutrition: string|null, tags: list<string>|null}|null
     */
    #[Computed]
    public function recipe(): ?array
    {
        return Recipes::find((int) $this->param('id'));
    }

    /**
     * @return array{id: int, name: string}|null
     */
    #[Computed]
    public function parent(): ?array
    {
        $parentId = $this->recipe['parent_id'] ?? null;

        return $parentId === null ? null : Recipes::find($parentId);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function remixes(): array
    {
        $id = $this->recipe['id'] ?? null;

        return collect(Recipes::all())->where('parent_id', $id)->whereNotNull('parent_id')->values()->all();
    }

    /**
     * The "Details" rows the web app shows, skipping blank fields.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function details(): array
    {
        return collect([
            'Servings' => $this->recipe['servings'] ?? null,
            'Prep time' => $this->recipe['prep_time'] ?? null,
            'Cook time' => $this->recipe['cook_time'] ?? null,
            'Total time' => $this->recipe['total_time'] ?? null,
        ])->filter()->all();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function ingredients(): array
    {
        return $this->lines($this->recipe['ingredients'] ?? null);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function instructions(): array
    {
        return $this->lines($this->recipe['instructions'] ?? null);
    }

    public function openSource(): void
    {
        if (Recipes::isSourceUrl($this->recipe['source'] ?? null)) {
            Browser::inApp($this->recipe['source']);
        }
    }

    public function render(): View
    {
        return view('native.recipe-detail');
    }

    /**
     * Split the rich-text editor's HTML into plain-text rows: one per list item, or per paragraph/line when there is no list.
     *
     * @return list<string>
     */
    private function lines(?string $html): array
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
}
