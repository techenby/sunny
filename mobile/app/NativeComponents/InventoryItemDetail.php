<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsQueuedChange;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

class InventoryItemDetail extends NativeComponent
{
    use ChecksSunnySync;
    use ShowsQueuedChange;

    public string $error = '';

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
     * Every container this item is inside, from the top level down to its direct parent.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}>
     */
    #[Computed]
    public function path(): array
    {
        $path = [];
        $ancestor = $this->parent;

        while ($ancestor !== null && count($path) < 50) {
            array_unshift($path, $ancestor);
            $ancestor = $ancestor['parent_id'] === null ? null : Inventory::find($ancestor['parent_id']);
        }

        return $path;
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, photo_url: string|null, created_at: string, updated_at: string, children_count: int}>
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

    public function confirmDeleteItem(): void
    {
        if ($this->item === null) {
            return;
        }

        $count = count($this->children);
        $message = '“'.$this->item['name'].'” will be deleted for everyone on your team.';

        if ($count > 0) {
            $message .= ' '.trans_choice('The :count item inside it will move to the top level.|The :count items inside it will move to the top level.', $count);
        }

        Dialog::alert('Delete item?', $message, [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Delete', 'style' => 'destructive'],
        ])->id('delete-item')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'delete-item' && $label === 'Delete') {
            $this->deleteItem();
        } elseif ($id === 'discard-queued-change' && $label === 'Discard') {
            $this->discardQueuedChange();
        }
    }

    public function deleteItem(): void
    {
        if ($this->item === null) {
            return;
        }

        try {
            app(SunnyOutbox::class)->delete('items', $this->item['team_id'], $this->item['id']);
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to delete on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->back();
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status): void
    {
        if ($status === 'finished') {
            $this->refreshLocalSyncedData();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->item, $this->parent, $this->path, $this->children, $this->queuedChange);
    }

    /**
     * Jump to any container in the path. The direct parent goes through {@see openParent()} so it can pop back instead of pushing a duplicate.
     */
    public function openAncestor(int $id): void
    {
        if ($id === ($this->parent['id'] ?? null)) {
            $this->openParent();

            return;
        }

        if (in_array($id, array_column($this->path, 'id'), true)) {
            $this->navigate('/inventory/'.$id, ['from' => $this->item['id']]);
        }
    }

    protected function queuedResource(): string
    {
        return 'items';
    }

    protected function queuedRecordId(): ?int
    {
        return $this->item['id'] ?? null;
    }

    public function render(): View
    {
        return view('native.inventory-item-detail');
    }
}
