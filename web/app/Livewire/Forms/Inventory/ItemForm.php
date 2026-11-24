<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Inventory;

use App\Actions\Inventory\CreateItem;
use App\Actions\Inventory\UpdateItem;
use App\Actions\Photos\CreateThumbnail;
use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
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
            unset($data['photo']);

            (new UpdateItem)->handle($this->editingItem, $data);
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
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where('team_id', $this->editingItem->team_id ?? Auth::user()->current_team_id),
                Rule::notIn($this->editingItem ? [$this->editingItem->id, ...$this->editingItem->descendantIds()] : []),
            ],
            'metadata' => ['nullable', 'array'],
            'metadata.*.key' => ['nullable', 'string', 'max:255', 'distinct'],
            'metadata.*.value' => ['required_with:metadata.*.key', 'nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120', CreateThumbnail::DIMENSIONS_RULE],
        ];
    }
}
