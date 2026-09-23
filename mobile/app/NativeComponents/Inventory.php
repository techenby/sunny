<?php

namespace App\NativeComponents;

use App\Enums\ItemType;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class Inventory extends NativeComponent
{
    /**
     * Placeholder items shaped like the sunnyhome.app Item model, until the app syncs with its API.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null}>
     */
    public static function all(): array
    {
        return [
            ['id' => 1, 'parent_id' => null, 'type' => ItemType::Location, 'name' => 'Kitchen', 'metadata' => null],
            ['id' => 2, 'parent_id' => 1, 'type' => ItemType::Bin, 'name' => 'Pantry', 'metadata' => null],
            ['id' => 3, 'parent_id' => 2, 'type' => ItemType::Item, 'name' => 'Canned tomatoes', 'metadata' => ['quantity' => '6', 'expires' => 'March 2027']],
            ['id' => 4, 'parent_id' => 2, 'type' => ItemType::Item, 'name' => 'Olive oil', 'metadata' => ['quantity' => '2']],
            ['id' => 5, 'parent_id' => 2, 'type' => ItemType::Item, 'name' => 'Basmati rice', 'metadata' => ['size' => '10 lb bag']],
            ['id' => 6, 'parent_id' => null, 'type' => ItemType::Location, 'name' => 'Garage', 'metadata' => null],
            ['id' => 7, 'parent_id' => 6, 'type' => ItemType::Bin, 'name' => 'Tool chest', 'metadata' => ['drawer' => 'Top']],
            ['id' => 8, 'parent_id' => 7, 'type' => ItemType::Item, 'name' => 'Cordless drill', 'metadata' => ['brand' => 'DeWalt', 'model' => 'DCD771']],
            ['id' => 9, 'parent_id' => 7, 'type' => ItemType::Item, 'name' => 'Tape measure', 'metadata' => ['length' => '25 ft']],
            ['id' => 10, 'parent_id' => 6, 'type' => ItemType::Item, 'name' => 'Camping tent', 'metadata' => ['capacity' => '4 people']],
            ['id' => 11, 'parent_id' => null, 'type' => ItemType::Location, 'name' => 'Basement', 'metadata' => null],
            ['id' => 12, 'parent_id' => 11, 'type' => ItemType::Bin, 'name' => 'Holiday decorations', 'metadata' => ['color' => 'Red lid']],
            ['id' => 13, 'parent_id' => 12, 'type' => ItemType::Item, 'name' => 'String lights', 'metadata' => ['quantity' => '3']],
            ['id' => 14, 'parent_id' => 12, 'type' => ItemType::Item, 'name' => 'Ornaments', 'metadata' => null],
            ['id' => 15, 'parent_id' => 11, 'type' => ItemType::Item, 'name' => 'Spare light bulbs', 'metadata' => ['quantity' => '8', 'type' => 'LED, 60 W equivalent']],
        ];
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null}|null
     */
    public static function find(int $id): ?array
    {
        return collect(static::all())->firstWhere('id', $id);
    }

    /**
     * The direct children of an item (or the top-level items when null), sorted by name, with their own child counts.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, children_count: int}>
     */
    public static function childrenOf(?int $parentId): array
    {
        $items = collect(static::all());

        return $items
            ->where('parent_id', $parentId)
            ->sortBy('name')
            ->map(fn (array $item): array => [
                ...$item,
                'children_count' => $items->where('parent_id', $item['id'])->count(),
            ])
            ->values()
            ->all();
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
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, children_count: int}>
     */
    #[Computed(persist: true)]
    public function items(): array
    {
        return static::childrenOf(null);
    }

    public function render(): View
    {
        return view('native.inventory');
    }
}
