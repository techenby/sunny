<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\MealPlanItem;
use App\Models\MealSlot;
use App\Models\MealTemplateItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A grid of meal slots by day, where each cell's meals can be added,
 * removed, and reordered. The meal plan addresses a cell by date and the
 * template by weekday, so each page says how to find and create the items
 * behind a cell.
 */
abstract class MealGrid extends Component
{
    public ?int $addingSlotId = null;

    public string|int|null $addingDay = null;

    public string $meal = '';

    /** @return BaseCollection<int, string> */
    #[Computed]
    public function recipeNames(): BaseCollection
    {
        return $this->team()->recipes()->orderBy('name')->pluck('name')->unique()->values();
    }

    public function startAdding(int $slotId, string|int $day): void
    {
        $this->addingSlotId = $this->slot($slotId)->id;
        $this->addingDay = $day;
        $this->meal = '';
        $this->resetValidation('meal');

        $this->modal('add-meal')->show();
    }

    public function addMeal(): void
    {
        $this->meal = trim($this->meal);

        $this->validate(['meal' => ['required', 'string', 'max:255']]);

        // Picking a suggestion links the recipe; anything else is free text.
        $recipe = $this->team()->recipes()->whereRaw('lower(name) = ?', [mb_strtolower($this->meal)])->first();

        $this->createCellItem($this->slot((int) $this->addingSlotId), $this->addingDay, [
            'recipe_id' => $recipe?->id,
            'name' => $recipe->name ?? $this->meal,
        ]);

        $this->meal = '';
        $this->modal('add-meal')->close();
        $this->refreshCells();
    }

    public function removeMeal(int $slotId, string|int $day, int $index): void
    {
        $this->cellItems($this->slot($slotId), $day)->get($index)?->delete();

        $this->refreshCells();
    }

    public function moveMeal(int $slotId, string|int $day, int $index, int $direction): void
    {
        $items = $this->cellItems($this->slot($slotId), $day)->values();
        $target = $index + ($direction < 0 ? -1 : 1);

        if (! $items->has($index) || ! $items->has($target)) {
            return;
        }

        $order = $items->all();
        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

        foreach (array_values($order) as $position => $item) {
            $item->update(['position' => $position + 1]);
        }

        $this->refreshCells();
    }

    /**
     * The items behind a cell, ready to edit.
     *
     * @return Collection<int, MealPlanItem|MealTemplateItem>
     */
    abstract protected function cellItems(MealSlot $slot, string|int $day): Collection;

    /** @param  array{recipe_id: ?int, name: string}  $attributes */
    abstract protected function createCellItem(MealSlot $slot, string|int $day, array $attributes): void;

    abstract protected function refreshCells(): void;

    protected function slot(int $slotId): MealSlot
    {
        return $this->team()->mealSlots()->findOrFail($slotId);
    }

    protected function team(): Team
    {
        return Auth::user()->currentTeam;
    }
}
