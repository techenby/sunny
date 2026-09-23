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
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}>
     */
    public static function all(): array
    {
        return [
            ['id' => 1, 'parent_id' => null, 'type' => ItemType::Location, 'name' => 'Kitchen', 'metadata' => null, 'created_at' => '2026-03-01 10:00:00', 'updated_at' => '2026-03-01 10:00:00'],
            ['id' => 2, 'parent_id' => 1, 'type' => ItemType::Bin, 'name' => 'Pantry', 'metadata' => null, 'created_at' => '2026-03-01 10:05:00', 'updated_at' => '2026-03-01 10:05:00'],
            ['id' => 3, 'parent_id' => 2, 'type' => ItemType::Item, 'name' => 'Canned tomatoes', 'metadata' => ['quantity' => '6', 'expires' => 'March 2027'], 'created_at' => '2026-08-12 17:30:00', 'updated_at' => '2026-09-22 18:45:00'],
            ['id' => 4, 'parent_id' => 2, 'type' => ItemType::Item, 'name' => 'Olive oil', 'metadata' => ['quantity' => '2'], 'created_at' => '2026-08-12 17:32:00', 'updated_at' => '2026-08-12 17:32:00'],
            ['id' => 5, 'parent_id' => 2, 'type' => ItemType::Item, 'name' => 'Basmati rice', 'metadata' => ['size' => '10 lb bag'], 'created_at' => '2026-09-10 11:00:00', 'updated_at' => '2026-09-10 11:00:00'],
            ['id' => 6, 'parent_id' => null, 'type' => ItemType::Location, 'name' => 'Garage', 'metadata' => null, 'created_at' => '2026-03-01 10:10:00', 'updated_at' => '2026-03-01 10:10:00'],
            ['id' => 7, 'parent_id' => 6, 'type' => ItemType::Bin, 'name' => 'Tool chest', 'metadata' => ['drawer' => 'Top'], 'created_at' => '2026-03-02 14:00:00', 'updated_at' => '2026-07-19 09:20:00'],
            ['id' => 8, 'parent_id' => 7, 'type' => ItemType::Item, 'name' => 'Cordless drill', 'metadata' => ['brand' => 'DeWalt', 'model' => 'DCD771'], 'created_at' => '2026-04-05 13:15:00', 'updated_at' => '2026-09-15 20:05:00'],
            ['id' => 9, 'parent_id' => 7, 'type' => ItemType::Item, 'name' => 'Tape measure', 'metadata' => ['length' => '25 ft'], 'created_at' => '2026-04-05 13:20:00', 'updated_at' => '2026-04-05 13:20:00'],
            ['id' => 10, 'parent_id' => 6, 'type' => ItemType::Item, 'name' => 'Camping tent', 'metadata' => ['capacity' => '4 people'], 'created_at' => '2026-05-22 16:40:00', 'updated_at' => '2026-05-22 16:40:00'],
            ['id' => 11, 'parent_id' => null, 'type' => ItemType::Location, 'name' => 'Basement', 'metadata' => null, 'created_at' => '2026-03-01 10:15:00', 'updated_at' => '2026-03-01 10:15:00'],
            ['id' => 12, 'parent_id' => 11, 'type' => ItemType::Bin, 'name' => 'Holiday decorations', 'metadata' => ['color' => 'Red lid'], 'created_at' => '2026-03-03 19:00:00', 'updated_at' => '2026-03-03 19:00:00'],
            ['id' => 13, 'parent_id' => 12, 'type' => ItemType::Item, 'name' => 'String lights', 'metadata' => ['quantity' => '3'], 'created_at' => '2026-03-03 19:05:00', 'updated_at' => '2026-09-20 12:00:00'],
            ['id' => 14, 'parent_id' => 12, 'type' => ItemType::Item, 'name' => 'Ornaments', 'metadata' => null, 'created_at' => '2026-03-03 19:10:00', 'updated_at' => '2026-03-03 19:10:00'],
            ['id' => 15, 'parent_id' => 11, 'type' => ItemType::Item, 'name' => 'Spare light bulbs', 'metadata' => ['quantity' => '8', 'type' => 'LED, 60 W equivalent'], 'created_at' => '2026-06-30 08:50:00', 'updated_at' => '2026-06-30 08:50:00'],
        ];
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}|null
     */
    public static function find(int $id): ?array
    {
        return collect(static::all())->firstWhere('id', $id);
    }

    /**
     * The most recently added or updated items, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 3): array
    {
        return collect(static::all())
            ->sortByDesc('updated_at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The direct children of an item (or the top-level items when null), sorted by name, with their own child counts.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string, children_count: int}>
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
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string, children_count: int}>
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
