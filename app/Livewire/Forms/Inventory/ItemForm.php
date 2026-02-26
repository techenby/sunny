<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Inventory;

use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class ItemForm extends Form
{
    public ?Item $editingItem = null;

    public string $name = '';

    public ?int $container_id = null;

    public function load(Item $item)
    {
        $this->fill([
            'editingItem' => $item,
            'name' => $item->name,
            'container_id' => $item->container_id,
        ]);
    }

    public function save()
    {
        $data = $this->validate();

        if ($this->editingItem) {
            $this->editingItem->update($data);
        } else {
            Auth::user()->currentTeam->items()->create($data);
        }

        $this->reset();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'container_id' => ['nullable', 'integer', 'exists:containers,id'],
        ];
    }
}
