<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Inventory;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ItemForm extends Form
{
    public ?Item $editingItem = null;

    public string $name = '';

    public ?string $type = null;

    public ?int $parent_id = null;

    /** @var array<int, array{key: string, value: string}> */
    public array $metadata = [];

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
            $this->editingItem->update($data);
        } else {
            Auth::user()->currentTeam->items()->create($data);
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
            'metadata.*.key' => ['nullable', 'string', 'max:255'],
            'metadata.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }
}
