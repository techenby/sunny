<?php

namespace App\NativeComponents;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class Inventory extends NativeComponent
{
    /**
     * The last downloaded inventory, read entirely from the local database.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}>
     */
    public static function all(): array
    {
        return Item::forCurrentServer()->orderBy('id')->get()
            ->map(fn (Item $item): array => [...$item->toArray(), 'type' => $item->type])->all();
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}|null
     */
    public static function find(int $id): ?array
    {
        $item = Item::forCurrentServer()->find($id);

        return $item ? [...$item->toArray(), 'type' => $item->type] : null;
    }

    /**
     * The most recently added or updated items, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 3): array
    {
        return Item::forCurrentServer()->orderByDesc('updated_at')->limit($limit)->get()
            ->map(fn (Item $item): array => [...$item->toArray(), 'type' => $item->type])->all();
    }

    /**
     * The direct children of an item (or the top-level items when null), sorted by name, with their own child counts.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string, children_count: int}>
     */
    public static function childrenOf(?int $parentId): array
    {
        return Item::forCurrentServer()->where('parent_id', $parentId)->orderBy('name')->withCount('children')->get()
            ->map(fn (Item $item): array => [...$item->toArray(), 'type' => $item->type])->all();
    }

    /**
     * The ids of everything nested inside an item, at any depth.
     *
     * @return list<int>
     */
    public static function descendantIdsOf(int $id): array
    {
        return collect(static::all())
            ->where('parent_id', $id)
            ->flatMap(fn (array $child): array => [$child['id'], ...static::descendantIdsOf($child['id'])])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function selectableParents(): array
    {
        return collect(static::all())
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string, children_count: int}>
     */
    #[Computed]
    public function items(): array
    {
        return static::childrenOf(null);
    }

    public function render(): View
    {
        return view('native.inventory');
    }
}
