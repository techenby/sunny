<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesInventoryItemForm;
use App\Enums\ItemType;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class EditInventoryItem extends NativeComponent
{
    use ChecksSunnySync;
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
        if ($this->item === null) {
            $this->error = 'This record could not be found.';

            return;
        }

        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('items', $this->itemPayload(true), $this->item['id']);
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
