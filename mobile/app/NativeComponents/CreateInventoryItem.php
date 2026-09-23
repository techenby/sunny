<?php

namespace App\NativeComponents;

use App\Enums\ItemType;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;

class CreateInventoryItem extends NativeComponent
{
    /** The destination option standing in for "no container at all". */
    public const TOP_LEVEL = 'Top level';

    public string $name = '';

    /** Index into {@see ItemType::cases()} — bound to the type selector. */
    public int $typeIndex = 0;

    public string $parentName = self::TOP_LEVEL;

    /**
     * Metadata as the editable key/value pairs the form renders, mirroring
     * App\Livewire\Forms\Inventory\ItemForm in the sunnyhome.app web app.
     *
     * @var list<array{key: string, value: string}>
     */
    public array $metadata = [];

    /** On-device path to the captured or picked photo, once there is one. */
    public ?string $photoPath = null;

    public string $error = '';

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
     * @return list<string>
     */
    #[Computed]
    public function parentOptions(): array
    {
        return [self::TOP_LEVEL, ...array_values(Inventory::selectableParents())];
    }

    #[Computed]
    public function parentId(): ?int
    {
        $id = array_search($this->parentName, Inventory::selectableParents(), strict: true);

        return $id === false ? null : $id;
    }

    public function takePhoto(): void
    {
        Camera::getPhoto()->start();
    }

    public function choosePhoto(): void
    {
        Camera::pickImages('image')->start();
    }

    public function removePhoto(): void
    {
        $this->photoPath = null;
    }

    #[On(PhotoTaken::class)]
    public function photoTaken(string $path): void
    {
        $this->photoPath = $path;
    }

    /**
     * The gallery picker hands back one entry per selection, each shaped
     * `{path, mimeType, extension, type}` on both platforms. Single-select is
     * configured in choosePhoto(), so only the first entry is ever used.
     *
     * @param  list<array{path?: string}>  $files
     */
    #[On(MediaSelected::class)]
    public function mediaSelected(bool $success, array $files): void
    {
        if (! $success) {
            return;
        }

        $path = $files[0]['path'] ?? null;

        if ($path !== null) {
            $this->photoPath = $path;
        }
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

    public function save(): void
    {
        if (trim($this->name) === '') {
            $this->error = 'Give the item a name.';

            return;
        }

        $keys = collect($this->metadata)
            ->map(fn (array $pair): string => trim($pair['key']))
            ->filter();

        if ($keys->duplicates()->isNotEmpty()) {
            $this->error = 'Metadata keys must be unique.';

            return;
        }

        $missingValue = collect($this->metadata)
            ->contains(fn (array $pair): bool => trim($pair['key']) !== '' && trim($pair['value']) === '');

        if ($missingValue) {
            $this->error = 'Give every metadata field a value.';

            return;
        }

        $this->error = '';

        // Inventory is still the hardcoded placeholder list in Inventory::all(),
        // so there is nowhere to write to yet — return to the list once the
        // form is valid, until the sunnyhome.app API is wired up.
        $this->back();
    }

    public function render(): View
    {
        return view('native.create-inventory-item');
    }
}
