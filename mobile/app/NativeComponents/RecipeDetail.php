<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

class RecipeDetail extends NativeComponent
{
    use ChecksSunnySync;

    /**
     * Ingredients ticked off while cooking, by position. Kept on this screen only.
     *
     * @var list<int>
     */
    public array $checkedIngredients = [];

    /**
     * @return array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: string|null, instructions: string|null, notes: string|null, nutrition: string|null, tags: list<string>|null, created_at: string, updated_at: string}|null
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
        return Recipes::lines($this->recipe['ingredients'] ?? null);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function instructions(): array
    {
        return Recipes::lines($this->recipe['instructions'] ?? null);
    }

    public function toggleIngredient(int $index): void
    {
        $this->checkedIngredients = in_array($index, $this->checkedIngredients, true)
            ? array_values(array_diff($this->checkedIngredients, [$index]))
            : [...$this->checkedIngredients, $index];
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status): void
    {
        if ($status === 'finished') {
            $this->refreshLocalSyncedData();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->recipe, $this->parent, $this->remixes, $this->details, $this->ingredients, $this->instructions);
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
}
