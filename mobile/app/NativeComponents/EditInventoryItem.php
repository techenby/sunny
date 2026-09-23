<?php

namespace App\NativeComponents;

use App\Concerns\ManagesInventoryItemForm;
use App\Enums\ItemType;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class EditInventoryItem extends NativeComponent
{
    use ManagesInventoryItemForm;

    public function mount(): void
    {
        if ($this->item !== null) {
            $this->fillFromItem($this->item);
        }
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}|null
     */
    #[Computed]
    public function item(): ?array
    {
        return Inventory::find((int) $this->param('id'));
    }

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        // Inventory is still the hardcoded placeholder list in Inventory::all(),
        // so the edits cannot be written back yet — return to the item once
        // the form is valid, until the API is wired up.
        $this->back();
    }

    public function render(): View
    {
        return view('native.edit-inventory-item');
    }

    /**
     * An item cannot be moved inside itself or anything it contains.
     *
     * @return list<int>
     */
    protected function unselectableParentIds(): array
    {
        if ($this->item === null) {
            return [];
        }

        return [$this->item['id'], ...Inventory::descendantIdsOf($this->item['id'])];
    }
}
