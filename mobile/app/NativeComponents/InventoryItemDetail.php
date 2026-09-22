<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class InventoryItemDetail extends NativeComponent
{
    /**
     * @return array{id: int, name: string, location: string, spot: string, quantity: int, notes: string}|null
     */
    #[Computed]
    public function item(): ?array
    {
        return Inventory::find((int) $this->param('id'));
    }

    public function render(): View
    {
        return view('native.inventory-item-detail');
    }
}
