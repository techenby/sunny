<?php

namespace App\Concerns;

use App\Enums\ItemType;
use App\NativeComponents\Inventory;
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

    /** The destination option standing in for "no container at all". */
    public const TOP_LEVEL = 'Top level';

    public string $name = '';

    /** Index into {@see ItemType::cases()} — bound to the type selector. */
    public int $typeIndex = 0;

    public string $parentName = self::TOP_LEVEL;

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
        $this->name = $item['name'];
        $this->typeIndex = (int) array_search($item['type'], ItemType::cases(), strict: true);
        $this->parentName = $item['parent_id'] === null
            ? self::TOP_LEVEL
            : ($this->parentChoices[$item['parent_id']] ?? self::TOP_LEVEL);
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
     * The items this one may be placed inside, keyed by id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function parentChoices(): array
    {
        return array_diff_key(Inventory::selectableParents(), array_flip($this->unselectableParentIds()));
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function parentOptions(): array
    {
        return [self::TOP_LEVEL, ...array_values($this->parentChoices)];
    }

    #[Computed]
    public function parentId(): ?int
    {
        $id = array_search($this->parentName, $this->parentChoices, strict: true);

        return $id === false ? null : $id;
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

    protected function validationError(): string
    {
        if (trim($this->name) === '') {
            return 'Give the item a name.';
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
