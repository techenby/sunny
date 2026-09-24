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

        $this->error = 'Saving changes is not available yet. Please edit this on the Sunny website.';
    }

    public function render(): View
    {
        return view('native.create-inventory-item');
    }
}
