<?php

namespace App\Concerns;

use App\Enums\ItemType;
use App\NativeComponents\Inventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Native\Mobile\Attributes\Computed;

/**
 * The inventory item form's state and rules, shared by the create and edit
 * screens.
 *
 * Mirrors App\Livewire\Forms\Inventory\ItemForm in the sunnyhome.app web app.
 */
trait ManagesInventoryItemForm
{
    use CapturesPhoto;
    use SavesSunnyRecord;

    public string $name = '';

    /** Index into {@see ItemType::cases()} — bound to the type selector. */
    public int $typeIndex = 0;

    /** The item this one lives inside, or null for the top level. */
    public ?int $parentId = null;

    public bool $showParentPicker = false;

    public string $parentSearch = '';

    /** The container whose contents the destination picker is showing, or null for the top level. */
    public ?int $parentBrowseId = null;

    /**
     * Metadata as the editable key/value pairs the form renders.
     *
     * @var list<array{key: string, value: string}>
     */
    public array $metadata = [];

    public string $error = '';

    /**
     * Load an existing item into the form.
     *
     * @param  array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}  $item
     */
    public function fillFromItem(array $item): void
    {
        $this->initializeTeam($item['team_id']);
        $this->existingPhotoUrl = $item['photo_url'] ?? null;
        $this->name = $item['name'];
        $this->typeIndex = (int) array_search($item['type'], ItemType::cases(), strict: true);
        $this->parentId = $this->parentChoice($item['parent_id']) === null ? null : $item['parent_id'];
        $this->metadata = collect($item['metadata'] ?? [])
            ->map(fn (string $value, string $key): array => ['key' => $key, 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * The labels for the type selector, in enum order so the bound index
     * lines up with {@see ItemType::cases()}.
     *
     * @return list<string>
     */
    #[Computed]
    public function typeOptions(): array
    {
        return array_map(fn (ItemType $type): string => $type->label(), ItemType::cases());
    }

    #[Computed]
    public function type(): ItemType
    {
        return ItemType::cases()[$this->typeIndex] ?? ItemType::Item;
    }

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

    public function addMetadata(): void
    {
        $this->metadata[] = ['key' => '', 'value' => ''];
    }

    public function removeMetadata(int $index): void
    {
        unset($this->metadata[$index]);

        $this->metadata = array_values($this->metadata);
    }

    public function setMetadataKey(int $index, string $key): void
    {
        $this->metadata[$index]['key'] = $key;
    }

    public function setMetadataValue(int $index, string $value): void
    {
        $this->metadata[$index]['value'] = $value;
    }

    /**
     * The metadata pairs collapsed into the shape the Item model stores,
     * dropping the blank rows the form leaves behind.
     *
     * @return array<string, string>|null
     */
    #[Computed]
    public function metadataMap(): ?array
    {
        return collect($this->metadata)
            ->filter(fn (array $pair): bool => trim($pair['key']) !== '')
            ->mapWithKeys(fn (array $pair): array => [trim($pair['key']) => $pair['value']])
            ->all() ?: null;
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

    protected function itemPayload(bool $editing = false): array
    {
        return [
            'name' => trim($this->name), 'type' => $this->type->value,
            'parent_id' => $this->parentId, 'metadata' => $this->metadataMap,
            ...($editing ? ['remove_photo' => $this->photoRemoved] : []),
        ];
    }

    protected function validationError(): string
    {
        if (trim($this->name) === '') {
            return 'Give the item a name.';
        }

        if (mb_strlen(trim($this->name)) > 255) {
            return 'The name is too long (255 characters max).';
        }
        if ($this->parentId !== null && $this->selectedParent === null) {
            return 'Choose a parent in the same team.';
        }

        $keys = collect($this->metadata)
            ->map(fn (array $pair): string => trim($pair['key']))
            ->filter();

        if ($keys->duplicates()->isNotEmpty()) {
            return 'Metadata keys must be unique.';
        }

        $missingValue = collect($this->metadata)
            ->contains(fn (array $pair): bool => trim($pair['key']) !== '' && trim($pair['value']) === '');

        if ($missingValue) {
            return 'Give every metadata field a value.';
        }

        return '';
    }
}
