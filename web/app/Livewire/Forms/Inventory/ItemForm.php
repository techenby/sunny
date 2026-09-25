<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Inventory;

use App\Actions\Inventory\CreateItem;
use App\Actions\Inventory\UpdateItem;
use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class ItemForm extends Form
{
    public ?Item $editingItem = null;

    public string $name = '';

    public ?string $type = null;

    public ?int $parent_id = null;

    /** @var array<int, array{key: string, value: string}> */
    public array $metadata = [];

    public ?TemporaryUploadedFile $photo = null;

    public ?string $existingPhotoUrl = null;

    public bool $removePhoto = false;

    public function load(Item $item): void
    {
        $metadata = collect($item->metadata ?? [])
            ->map(fn (string $value, string $key) => ['key' => $key, 'value' => $value])
            ->values()
            ->all();

        $this->fill([
            'editingItem' => $item,
            'name' => $item->name,
            'type' => $item->type->value,
            'parent_id' => $item->parent_id,
            'metadata' => $metadata,
            'existingPhotoUrl' => $item->photo_path ? Storage::temporaryUrl($item->photo_path, now()->addMinutes(30)) : null,
        ]);
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

    public function save(): void
    {
        $data = $this->validate();

        $data['metadata'] = collect($data['metadata'] ?? [])
            ->filter(fn (array $pair) => $pair['key'] !== '')
            ->mapWithKeys(fn (array $pair) => [$pair['key'] => $pair['value']])
            ->all() ?: null;

        if ($this->editingItem) {
            (new UpdateItem)->handle($this->editingItem, $data, $this->removePhoto);
        } else {
            (new CreateItem)->handle(Auth::user()->currentTeam, $data);
        }

        $this->reset();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:items,id'],
            'metadata' => ['nullable', 'array'],
            'metadata.*.key' => ['nullable', 'string', 'max:255', 'distinct'],
            'metadata.*.value' => ['required_with:metadata.*.key', 'nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
