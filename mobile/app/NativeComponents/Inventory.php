<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

class Inventory extends NativeComponent
{
    use ChecksSunnySync;

    public string $search = '';

    /**
     * The last downloaded inventory, read entirely from the local database.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}>
     */
    public static function all(): array
    {
        return Item::forActiveTeam()->orderBy('id')->get()
            ->map(fn (Item $item): array => [...$item->toArray(), 'type' => $item->type])->all();
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}|null
     */
    public static function find(int $id): ?array
    {
        $item = Item::forActiveTeam()->where(fn ($query) => $query->whereKey($id)->orWhere('local_id', $id))->first();

        return $item ? [...$item->toArray(), 'type' => $item->type] : null;
    }

    /**
     * The most recently added or updated items, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 3): array
    {
        return Item::forActiveTeam()->orderByDesc('updated_at')->limit($limit)->get()
            ->map(fn (Item $item): array => [...$item->toArray(), 'type' => $item->type])->all();
    }

    /**
     * The direct children of an item (or the top-level items when null), sorted by name, with their own child counts.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string, children_count: int}>
     */
    public static function childrenOf(?int $parentId): array
    {
        return Item::forActiveTeam()->where('parent_id', $parentId)->orderBy('name')->withCount('children')->get()
            ->map(fn (Item $item): array => [...$item->toArray(), 'type' => $item->type])->all();
    }

    /**
     * The ids of everything nested inside an item, at any depth, in no particular order.
     *
     * @return list<int>
     */
    public static function descendantIdsOf(int $id): array
    {
        $childIds = Item::forActiveTeam()->whereNotNull('parent_id')->get(['id', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn ($children): array => $children->pluck('id')->all());

        $descendants = [];
        $pending = $childIds->get($id, []);

        while ($pending !== []) {
            $childId = array_pop($pending);

            if ($childId === $id || isset($descendants[$childId])) {
                continue;
            }

            $descendants[$childId] = true;
            array_push($pending, ...$childIds->get($childId, []));
        }

        return array_keys($descendants);
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string, children_count: int}>
     */
    #[Computed]
    public function items(): array
    {
        return static::childrenOf(null);
    }

    /**
     * Every item matching the search, at any depth, with the name of the item it lives in.
     *
     * @return list<array{id: int, type: ItemType, name: string, location: string|null}>
     */
    #[Computed]
    public function searchResults(): array
    {
        if ($this->search === '') {
            return [];
        }

        $items = collect(static::all());
        $names = $items->pluck('name', 'id');

        return $items
            ->filter(fn (array $item): bool => Str::contains($item['name'], $this->search, ignoreCase: true))
            ->sortBy('name')
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'type' => $item['type'],
                'name' => $item['name'],
                'location' => $names[$item['parent_id']] ?? null,
            ])
            ->values()
            ->all();
    }

    public function updateSearch(string $query): void
    {
        $this->search = trim($query);
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
        unset($this->items, $this->searchResults);
    }

    public function render(): View
    {
        return view('native.inventory');
    }
}
