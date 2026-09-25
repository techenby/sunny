<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('List')] class extends Component
{
    public Checklist $checklist;

    public string $newItem = '';

    /** @return Collection<int, ChecklistItem> */
    #[Computed]
    public function items(): Collection
    {
        return $this->checklist->items()->with('completedBy')->get();
    }

    public function addItem(): void
    {
        $this->authorize('update', $this->checklist);

        $this->newItem = trim($this->newItem);

        if ($this->newItem === '') {
            return;
        }

        $this->validate(['newItem' => ['string', 'max:255']]);

        $this->checklist->items()->create(['name' => $this->newItem]);
        $this->newItem = '';

        unset($this->items);
    }

    public function clearCompleted(): void
    {
        $this->authorize('update', $this->checklist);

        $this->checklist->items()->completed()->delete();

        unset($this->items);
    }

    public function removeItem(int $itemId): void
    {
        $this->authorize('update', $this->checklist);

        $this->item($itemId)->delete();

        unset($this->items);
    }

    public function resetList(): void
    {
        $this->authorize('update', $this->checklist);

        $this->checklist->reset();

        unset($this->items);
    }

    public function toggle(int $itemId): void
    {
        $this->authorize('update', $this->checklist);

        $this->item($itemId)->toggle(Auth::user());

        unset($this->items);
    }

    private function item(int $itemId): ChecklistItem
    {
        return $this->checklist->items()->findOrFail($itemId);
    }
};
