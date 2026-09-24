<?php

namespace App\NativeComponents;

use App\Concerns\ManagesInventoryItemForm;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class CreateInventoryItem extends NativeComponent
{
    use ManagesInventoryItemForm;

    public function mount(): void
    {
        $this->initializeTeam();
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
