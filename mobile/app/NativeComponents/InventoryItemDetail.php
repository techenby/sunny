<?php

namespace App\NativeComponents;

use App\Enums\ItemType;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class InventoryItemDetail extends NativeComponent
{
    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null}|null
     */
    #[Computed]
    public function item(): ?array
    {
        return Inventory::find((int) $this->param('id'));
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null}|null
     */
    #[Computed]
    public function parent(): ?array
    {
        $parentId = $this->item['parent_id'] ?? null;

        return $parentId === null ? null : Inventory::find($parentId);
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, children_count: int}>
     */
    #[Computed]
    public function children(): array
    {
        return $this->item === null ? [] : Inventory::childrenOf($this->item['id']);
    }

    public function render(): View
    {
        return view('native.inventory-item-detail');
    }
}
