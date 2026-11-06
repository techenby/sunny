<?php

use App\Actions\MealPlanning\CollectMealIngredients;
use App\Actions\MealPlanning\OverrideMealSlot;
use App\Actions\MealPlanning\ResolveMealPlan;
use App\Enums\ChecklistType;
use App\Livewire\MealGrid;
use App\Models\Checklist;
use App\Models\MealSlot;
use App\Models\Recipe;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

new class extends MealGrid
{
    /** Any date in the week being shown; empty means this week. */
    #[Url]
    public string $week = '';

    public string $shoppingFrom = '';

    public string $shoppingThrough = '';

    /** @var list<string> keys of the ingredient lines to add, as "recipeId:index" */
    public array $selectedLines = [];

    public ?int $shoppingListId = null;

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    #[Computed]
    public function range(): array
    {
        $date = rescue(fn () => CarbonImmutable::parse($this->week), report: false) ?? $this->team()->today();

        return ResolveMealPlan::week($this->team(), $date);
    }

    #[Computed]
    public function today(): CarbonImmutable
    {
        return $this->team()->today();
    }

    #[Computed]
    public function days(): BaseCollection
    {
        return (new ResolveMealPlan)->handle($this->team(), ...$this->range);
    }

    /** @return BaseCollection<int, MealSlot> */
    #[Computed]
    public function mealSlots(): BaseCollection
    {
        return $this->days->flatMap(fn (array $day) => $day['slots']->pluck('slot'))->unique('id')->sortBy('position')->values();
    }

    public function previousWeek(): void
    {
        $this->week = $this->range[0]->subWeek()->toDateString();
        $this->refreshCells();
    }

    public function nextWeek(): void
    {
        $this->week = $this->range[0]->addWeek()->toDateString();
        $this->refreshCells();
    }

    public function thisWeek(): void
    {
        $this->week = '';
        $this->refreshCells();
    }

    public function resetSlot(int $slotId, string $date): void
    {
        $date = $this->date($date);

        if ($date->lte($this->team()->mealsSavedThrough())) {
            return;
        }

        $this->slot($slotId)->planSlots()->where('date', $date->toDateString())->delete();

        $this->refreshCells();
    }

    public function startShopping(): void
    {
        [$start, $end] = $this->range;
        $today = $this->team()->today();

        $this->shoppingFrom = $start->max($today)->min($end)->toDateString();
        $this->shoppingThrough = $end->toDateString();
        $this->shoppingListId ??= $this->shoppingLists->first()?->id;
        $this->selectAllLines();

        $this->modal('shopping')->show();
    }

    public function updatedShoppingFrom(): void
    {
        $this->selectAllLines();
    }

    public function updatedShoppingThrough(): void
    {
        $this->selectAllLines();
    }

    /** @return Collection<int, Checklist> */
    #[Computed]
    public function shoppingLists(): Collection
    {
        return $this->team()->checklists()->ofType(ChecklistType::Shopping)->orderBy('name')->get();
    }

    /** @return BaseCollection<int, array{recipe: Recipe, count: int, lines: list<string>}> */
    #[Computed]
    public function ingredientGroups(): BaseCollection
    {
        $from = rescue(fn () => CarbonImmutable::parse($this->shoppingFrom), report: false);
        $through = rescue(fn () => CarbonImmutable::parse($this->shoppingThrough), report: false);

        if (! $from || ! $through || $through->lt($from) || $from->diffInDays($through) > 31) {
            return collect();
        }

        return (new CollectMealIngredients)->handle($this->team(), $from, $through);
    }

    public function addToShoppingList(): void
    {
        $this->validate([
            'shoppingListId' => ['required', 'integer'],
            'selectedLines' => ['required', 'array', 'min:1'],
        ], [
            'selectedLines.required' => __('Choose at least one ingredient.'),
        ]);

        $list = $this->shoppingLists->firstWhere('id', $this->shoppingListId);

        if (! $list) {
            $this->addError('shoppingListId', __('Choose a shopping list.'));

            return;
        }

        $selected = array_flip($this->selectedLines);
        $added = 0;

        foreach ($this->ingredientGroups as $group) {
            foreach ($group['lines'] as $index => $line) {
                if (isset($selected[$group['recipe']->id . ':' . $index])) {
                    $list->items()->create(['name' => $line]);
                    $added++;
                }
            }
        }

        $this->modal('shopping')->close();

        Flux::toast(variant: 'success', text: trans_choice('{1} Added :count item to :list.|[2,*] Added :count items to :list.', $added, [
            'count' => $added,
            'list' => $list->name,
        ]));
    }

    protected function cellItems(MealSlot $slot, string|int $day): Collection
    {
        return (new OverrideMealSlot)->handle($slot, $this->date($day))->items()->get();
    }

    protected function createCellItem(MealSlot $slot, string|int $day, array $attributes): void
    {
        (new OverrideMealSlot)->handle($slot, $this->date($day))->items()->create($attributes);
    }

    protected function refreshCells(): void
    {
        unset($this->range, $this->days, $this->mealSlots);
    }

    private function selectAllLines(): void
    {
        unset($this->ingredientGroups);

        $this->selectedLines = $this->ingredientGroups
            ->flatMap(fn (array $group) => collect($group['lines'])->keys()->map(fn (int $index): string => $group['recipe']->id . ':' . $index))
            ->values()
            ->all();
    }

    private function date(string|int $value): CarbonImmutable
    {
        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) $value), report: false);

        abort_unless($date instanceof CarbonImmutable, 404);

        return $date;
    }
};
