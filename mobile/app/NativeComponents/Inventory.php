<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Enums\ItemType;
use App\Icons\Android;
use App\Icons\Ios;
use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\Facades\Scanner;
use Sunny\ItemScanner\Events\ScreenUncovered;
use Sunny\ItemScanner\Facades\ItemScanner;

class Inventory extends NativeComponent
{
    use ChecksSunnySync;

    protected const SCAN_ID = 'item-code';

    public string $search = '';

    public ?string $scannedPath = null;

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
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, photo_url: string|null, thumb_path: string|null, created_at: string, updated_at: string, children_count: int}>
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
     * @param  array{type: ItemType, children_count?: int}  $item
     */
    public static function summary(array $item): string
    {
        $count = $item['children_count'] ?? 0;

        if ($count > 0) {
            return trans_choice(':count thing inside|:count things inside', $count);
        }

        return $item['type'] === ItemType::Location ? 'Nothing inside yet' : $item['type']->label();
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, photo_url: string|null, thumb_path: string|null, created_at: string, updated_at: string, children_count: int}>
     */
    #[Computed]
    public function items(): array
    {
        return static::childrenOf(null);
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, photo_url: string|null, thumb_path: string|null, created_at: string, updated_at: string, children_count: int}>
     */
    #[Computed]
    public function places(): array
    {
        return array_values(array_filter($this->items, fn (array $item): bool => $item['type'] === ItemType::Location));
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, photo_url: string|null, thumb_path: string|null, created_at: string, updated_at: string, children_count: int}>
     */
    #[Computed]
    public function unplaced(): array
    {
        return array_values(array_filter($this->items, fn (array $item): bool => $item['type'] !== ItemType::Location));
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, photo_url: string|null, thumb_path: string|null, created_at: string, updated_at: string, where: string}>
     */
    #[Computed]
    public function searchResults(): array
    {
        if ($this->search === '') {
            return [];
        }

        $items = collect(static::all())->keyBy('id');

        return $items
            ->filter(fn (array $item): bool => Str::contains($item['name'], $this->search, ignoreCase: true))
            ->sortBy('name')
            ->map(fn (array $item): array => [...$item, 'where' => $this->whereItIs($item, $items)])
            ->values()
            ->all();
    }

    public function updateSearch(string $query): void
    {
        $this->search = trim($query);
    }

    /**
     * @return list<NavAction>
     */
    public function addMenu(): array
    {
        return [
            NavAction::make('add-one')->label('Add one thing')->icon(ios: Ios::Plus->value, android: Android::Add->value)->press('addOne'),
            NavAction::make('add-several')->label('Bulk add')->icon(ios: Ios::CameraViewfinder->value, android: Android::DocumentScanner->value)->press('scanItems'),
        ];
    }

    public function addOne(): void
    {
        $this->navigate('/inventory/create');
    }

    public function scanCode(): void
    {
        Scanner::scan()->prompt('Scan a Sunny label')->id(self::SCAN_ID);
    }

    public function scanItems(): void
    {
        $this->navigate('/inventory/scan');
    }

    #[On(CodeScanned::class)]
    public function codeScanned(string $data, string $format, ?string $id = null): void
    {
        if ($id !== self::SCAN_ID) {
            return;
        }

        $path = OpenItemLink::pathFor($data);

        if ($path === null) {
            Dialog::toast('That code isn’t a Sunny label.');

            return;
        }

        if (! ItemScanner::whenUncovered(self::SCAN_ID)) {
            $this->navigate($path);

            return;
        }

        $this->scannedPath = $path;
    }

    #[On(ScreenUncovered::class)]
    public function scannerClosed(string $id): void
    {
        if ($id !== self::SCAN_ID || $this->scannedPath === null) {
            return;
        }

        $path = $this->scannedPath;
        $this->scannedPath = null;

        $this->navigate($path);
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
        unset($this->items, $this->places, $this->unplaced, $this->searchResults);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    protected function whereItIs(array $item, Collection $items): string
    {
        $names = [];
        $ancestor = $items->get($item['parent_id']);

        while ($ancestor !== null && count($names) < 50) {
            array_unshift($names, $ancestor['name']);
            $ancestor = $items->get($ancestor['parent_id']);
        }

        if ($names !== []) {
            return 'In '.implode(' › ', $names);
        }

        return $item['type'] === ItemType::Location ? $item['type']->label() : 'Not in a place yet';
    }

    public function render(): View
    {
        return view('native.inventory');
    }
}
