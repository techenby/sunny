<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Checklist $checklist;

    public string $newItem = '';

    public string $newItemUrl = '';

    public ?int $editingItemId = null;

    public string $editingName = '';

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
        $this->newItemUrl = trim($this->newItemUrl);

        if ($this->newItem === '') {
            return;
        }

        $this->validate([
            'newItem' => ['string', 'max:255'],
            'newItemUrl' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $this->checklist->items()->create([
            'name' => $this->newItem,
            'url' => $this->newItemUrl ?: null,
        ]);
        $this->reset('newItem', 'newItemUrl');

        unset($this->items);
    }

    public function editItem(int $itemId): void
    {
        $this->authorize('update', $this->checklist);

        $this->editingName = $this->item($itemId)->name;
        $this->editingItemId = $itemId;
        $this->resetValidation('editingName');
    }

    public function updateItem(): void
    {
        $this->authorize('update', $this->checklist);

        $item = $this->item((int) $this->editingItemId);

        $this->editingName = trim($this->editingName);
        $this->validate(['editingName' => ['required', 'string', 'max:255']], attributes: ['editingName' => __('item name')]);

        $item->update(['name' => $this->editingName]);
        $this->cancelEdit();

        unset($this->items);
    }

    public function cancelEdit(): void
    {
        $this->reset('editingItemId', 'editingName');
        $this->resetValidation('editingName');
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
