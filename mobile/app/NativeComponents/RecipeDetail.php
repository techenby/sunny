<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsQueuedChange;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Facades\Browser;

class RecipeDetail extends Component
{
    use ChecksSunnySync;
    use ShowsQueuedChange;

    /**
     * Ingredients ticked off while cooking, as position => text so a tick stops
     * counting once a sync changes that line. Kept on this screen only.
     *
     * @var array<int, string>
     */
    public array $checkedIngredients = [];

    /**
     * @return array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: list<array<string, mixed>>|null, instructions: list<array<string, mixed>>|null, notes: string|null, nutrition: string|null, tags: list<string>|null, created_at: string, updated_at: string}|null
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
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function ingredients(): array
    {
        return $this->recipe['ingredients'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function instructions(): array
    {
        $number = 0;
        $rows = [];

        foreach ($this->recipe['instructions'] ?? [] as $row) {
            $rows[] = [...$row, 'number' => $this->isHeading($row) ? null : ++$number];
        }

        return $rows;
    }

    #[Computed]
    public function ingredientCount(): int
    {
        return collect($this->ingredients)->reject(fn (array $row): bool => $this->isHeading($row))->count();
    }

    #[Computed]
    public function checkedCount(): int
    {
        return collect($this->ingredients)->keys()->filter(fn (int $index): bool => $this->isChecked($index))->count();
    }

    /** @param  array<string, mixed>  $row */
    public function isHeading(array $row): bool
    {
        return ($row['type'] ?? null) === 'heading';
    }

    public function isChecked(int $index): bool
    {
        return isset($this->ingredients[$index]) && ($this->checkedIngredients[$index] ?? null) === $this->ingredients[$index]['text'];
    }

    public function toggleIngredient(int $index): void
    {
        if (! isset($this->ingredients[$index]) || $this->isHeading($this->ingredients[$index])) {
            return;
        }

        if ($this->isChecked($index)) {
            unset($this->checkedIngredients[$index]);
        } else {
            $this->checkedIngredients[$index] = $this->ingredients[$index]['text'];
        }

        unset($this->checkedCount);
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
        unset($this->recipe, $this->parent, $this->remixes, $this->details, $this->ingredients, $this->instructions, $this->ingredientCount, $this->checkedCount, $this->queuedChange);
    }

    public function openSource(): void
    {
        if (Recipes::isSourceUrl($this->recipe['source'] ?? null)) {
            Browser::inApp($this->recipe['source']);
        }
    }

    protected function queuedResource(): string
    {
        return 'recipes';
    }

    protected function queuedRecordId(): ?int
    {
        return $this->recipe['id'] ?? null;
    }

    public function render(): View
    {
        return view('native.recipe-detail');
    }
}
