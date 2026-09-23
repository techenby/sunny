<?php

namespace App\NativeComponents;

use App\Concerns\ManagesInventoryItemForm;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class CreateInventoryItem extends NativeComponent
{
    use ManagesInventoryItemForm;

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        // Inventory is still the hardcoded placeholder list in Inventory::all(),
        // so there is nowhere to write to yet — return to the list once the
        // form is valid, until the sunnyhome.app API is wired up.
        $this->back();
    }

    public function render(): View
    {
        return view('native.create-inventory-item');
    }
}
