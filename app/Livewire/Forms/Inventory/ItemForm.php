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

    public function load(Item $item): void
    {
        $this->fill([
            'editingItem' => $item,
            'name' => $item->name,
            'type' => $item->type->value,
            'parent_id' => $item->parent_id,
        ]);
    }

    public function save(): void
    {
        $data = $this->validate();

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
        ];
    }
}
