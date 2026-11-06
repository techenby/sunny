<?php

use App\Actions\MealPlanning\RemoveMealSlot;
use App\Livewire\MealGrid;
use App\Models\MealSlot;
use App\Models\MealTemplateItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Livewire\Attributes\Computed;

new class extends MealGrid
{
    public string $newSlot = '';

    /** @var array<int, string> */
    public array $slotNames = [];

    /** @return Collection<int, MealSlot> */
    #[Computed]
    public function mealSlots(): Collection
    {
        return $this->team()->mealSlots()->get();
    }

    /**
     * Weekday numbers (0 = Sunday) in the order the team's week runs, each
     * with the name to show for it.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function weekdays(): array
    {
        $start = CarbonImmutable::now()->startOfWeek((int) $this->team()->week_start);

        return collect(range(0, 6))
            ->mapWithKeys(fn (int $offset): array => [
                $start->addDays($offset)->dayOfWeek => $start->addDays($offset)->translatedFormat('l'),
            ])
            ->all();
    }

    /** @return BaseCollection<string, BaseCollection<int, MealTemplateItem>> */
    #[Computed]
    public function items(): BaseCollection
    {
        return $this->team()->mealTemplateItems()
            ->with('recipe')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (MealTemplateItem $item): string => $item->meal_slot_id . ':' . $item->weekday);
    }

    public function openSlots(): void
    {
        $this->syncSlotNames();
        $this->newSlot = '';
        $this->resetValidation();

        $this->modal('meal-slots')->show();
    }

    public function addSlot(): void
    {
        $this->newSlot = trim($this->newSlot);

        if ($this->newSlot === '') {
            return;
        }

        $this->validate(['newSlot' => ['string', 'max:255']]);

        $this->team()->mealSlots()->create(['name' => $this->newSlot]);
        $this->newSlot = '';

        $this->refreshCells();
        $this->syncSlotNames();
    }

    public function renameSlot(int $slotId): void
    {
        $slot = $this->slot($slotId);
        $this->slotNames[$slotId] = trim($this->slotNames[$slotId] ?? '');

        if ($this->slotNames[$slotId] === '') {
            $this->slotNames[$slotId] = $slot->name;

            return;
        }

        $this->validate(["slotNames.{$slotId}" => ['string', 'max:255']]);

        $slot->update(['name' => $this->slotNames[$slotId]]);

        $this->refreshCells();
    }

    public function moveSlot(int $slotId, int $direction): void
    {
        $slots = $this->mealSlots->values();
        $index = $slots->search(fn (MealSlot $slot): bool => $slot->id === $slotId);
        $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

        if ($target === null || ! $slots->has($target)) {
            return;
        }

        $order = $slots->all();
        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

        foreach (array_values($order) as $position => $slot) {
            $slot->update(['position' => $position + 1]);
        }

        $this->refreshCells();
    }

    public function removeSlot(int $slotId): void
    {
        (new RemoveMealSlot)->handle($this->slot($slotId));

        $this->refreshCells();
        $this->syncSlotNames();
    }

    protected function cellItems(MealSlot $slot, string|int $day): Collection
    {
        return $slot->templateItems()->where('weekday', $this->weekday($day))->get();
    }

    protected function createCellItem(MealSlot $slot, string|int $day, array $attributes): void
    {
        $slot->templateItems()->create([
            ...$attributes,
            'team_id' => $slot->team_id,
            'weekday' => $this->weekday($day),
        ]);
    }

    protected function refreshCells(): void
    {
        unset($this->mealSlots, $this->items);
    }

    private function syncSlotNames(): void
    {
        $this->slotNames = $this->mealSlots->pluck('name', 'id')->all();
    }

    private function weekday(string|int $day): int
    {
        abort_unless(is_numeric($day) && (int) $day >= 0 && (int) $day <= 6, 404);

        return (int) $day;
    }
};
