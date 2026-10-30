<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesInventoryItemForm;
use App\Enums\ItemType;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class CreateInventoryItem extends NativeComponent
{
    use ChecksSunnySync;
    use ManagesInventoryItemForm;

    public function mount(): void
    {
        $this->initializeTeam();
        $this->chooseType(ItemType::Item->value);

        $parent = $this->parentChoice((int) $this->data('parent'));
        if ($parent !== null) {
            $this->parentId = $parent['id'];
        }
    }

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('items', $this->itemPayload());
    }

    public function render(): View
    {
        return view('native.create-inventory-item');
    }
}
