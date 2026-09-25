<?php

namespace App\Concerns;

use App\Enums\ItemType;
use App\NativeComponents\Inventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Native\Mobile\Attributes\Computed;

/**
 * The destination picker for choosing which location, bin, or item
 * something lives inside. Expects the using component to provide a
 * `teamId` (see {@see SavesSunnyRecord}).
 */
trait ChoosesInventoryParent
{
    /** The item this one lives inside, or null for the top level. */
    public ?int $parentId = null;

    public bool $showParentPicker = false;

    public string $parentSearch = '';

    /** The container whose contents the destination picker is showing, or null for the top level. */
    public ?int $parentBrowseId = null;

    /**
     * The items this one may be placed inside, walked depth-first so each
     * location is followed by its bins and then its items.
     *
     * @return list<array{id: int, parent_id: int|null, name: string, type: ItemType, depth: int, path: string|null, children_count: int}>
     */
    #[Computed]
    public function parentChoices(): array
    {
        $items = collect(Inventory::all())->where('team_id', $this->teamId)->keyBy('id')
            ->except($this->unselectableParentIds());

        $childrenByParent = $items->groupBy(fn (array $item): int => $items->has($item['parent_id']) ? $item['parent_id'] : 0);

        return $this->flattenParentChoices($childrenByParent, 0, []);
    }

    /**
     * The rows the destination picker shows: every match while searching,
     * otherwise the contents of the container being browsed.
     *
     * @return list<array{id: int, parent_id: int|null, name: string, type: ItemType, depth: int, path: string|null, children_count: int}>
     */
    #[Computed]
    public function parentPickerRows(): array
    {
        $search = trim($this->parentSearch);
        $choices = collect($this->parentChoices);

        $rows = $search === ''
            ? $choices->where('parent_id', $this->browsedParent['id'] ?? null)
            : $choices->filter(fn (array $choice): bool => Str::contains($choice['name'], $search, ignoreCase: true));

        return $rows->values()->all();
    }

    /**
     * @return array{id: int, parent_id: int|null, name: string, type: ItemType, depth: int, path: string|null, children_count: int}|null
     */
    #[Computed]
    public function selectedParent(): ?array
    {
        return $this->parentChoice($this->parentId);
    }

    /**
     * @return array{id: int, parent_id: int|null, name: string, type: ItemType, depth: int, path: string|null, children_count: int}|null
     */
    #[Computed]
    public function browsedParent(): ?array
    {
        return $this->parentChoice($this->parentBrowseId);
    }

    /**
     * Open the picker on the container holding the current choice, so its
     * neighbours are one tap away.
     */
    public function openParentPicker(): void
    {
        $this->parentSearch = '';
        $this->parentBrowseId = $this->selectedParent['parent_id'] ?? null;
        $this->showParentPicker = true;
    }

    public function closeParentPicker(): void
    {
        $this->showParentPicker = false;
    }

    public function browseParent(int $id): void
    {
        if ($this->parentChoice($id) !== null) {
            $this->parentBrowseId = $id;
        }
    }

    public function browseUp(): void
    {
        $this->parentBrowseId = $this->browsedParent['parent_id'] ?? null;
    }

    public function selectParent(int $id): void
    {
        if ($this->parentChoice($id) !== null) {
            $this->parentId = $id;
        }

        $this->showParentPicker = false;
    }

    public function selectTopLevel(): void
    {
        $this->parentId = null;
        $this->showParentPicker = false;
    }

    /**
     * Item ids the destination picker must not offer.
     *
     * @return list<int>
     */
    protected function unselectableParentIds(): array
    {
        return [];
    }

    /**
     * @return array{id: int, parent_id: int|null, name: string, type: ItemType, depth: int, path: string|null, children_count: int}|null
     */
    protected function parentChoice(?int $id): ?array
    {
        return $id === null ? null : collect($this->parentChoices)->firstWhere('id', $id);
    }

    /**
     * @param  Collection<int, Collection<int, array<string, mixed>>>  $childrenByParent
     * @param  list<string>  $ancestors
     * @return list<array{id: int, parent_id: int|null, name: string, type: ItemType, depth: int, path: string|null, children_count: int}>
     */
    protected function flattenParentChoices(Collection $childrenByParent, int $parentKey, array $ancestors): array
    {
        return $childrenByParent->get($parentKey, collect())
            ->sortBy([
                fn (array $a, array $b): int => array_search($a['type'], ItemType::cases(), true) <=> array_search($b['type'], ItemType::cases(), true),
                fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']),
            ])
            ->flatMap(fn (array $item): array => [
                [
                    'id' => $item['id'],
                    'parent_id' => $ancestors === [] ? null : $item['parent_id'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'depth' => count($ancestors),
                    'path' => $ancestors === [] ? null : implode(' › ', $ancestors),
                    'children_count' => $childrenByParent->get($item['id'], collect())->count(),
                ],
                ...$this->flattenParentChoices($childrenByParent, $item['id'], [...$ancestors, $item['name']]),
            ])
            ->values()
            ->all();
    }
}
