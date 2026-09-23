<?php

namespace App\NativeComponents;

use App\Enums\ItemType;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class InventoryItemDetail extends NativeComponent
{
    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}|null
     */
    #[Computed]
    public function item(): ?array
    {
        return Inventory::find((int) $this->param('id'));
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}|null
     */
    #[Computed]
    public function parent(): ?array
    {
        $parentId = $this->item['parent_id'] ?? null;

        return $parentId === null ? null : Inventory::find($parentId);
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string, children_count: int}>
     */
    #[Computed]
    public function children(): array
    {
        return $this->item === null ? [] : Inventory::childrenOf($this->item['id']);
    }

    /**
     * Follow the "Inside" row up to the containing item.
     *
     * Rows pass the screen they were tapped from as `from` navigation data, so
     * when the parent is the screen directly below this one we pop back to the
     * live instance instead of pushing a second copy of it onto the stack —
     * otherwise the back button walks the user through the duplicate.
     */
    public function openParent(): void
    {
        $parent = $this->parent;

        if ($parent === null) {
            return;
        }

        if ($this->data('from') === $parent['id']) {
            $this->back();

            return;
        }

        $this->navigate('/inventory/'.$parent['id'], ['from' => $this->item['id']]);
    }

    public function render(): View
    {
        return view('native.inventory-item-detail');
    }
}
