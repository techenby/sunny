<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Inventory;

use App\Models\Container;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class ContainerForm extends Form
{
    public ?Container $editingContainer = null;

    public string $name = '';

    public string $type = 'location';

    public ?string $category = null;

    public mixed $parent_id = null;

    public function load(Container $container)
    {
        $this->fill([
            'editingContainer' => $container,
            'name' => $container->name,
            'type' => $container->type->value,
            'category' => $container->category ?? '',
            'parent_id' => $container->parent_id,
        ]);
    }

    public function save()
    {
        $data = $this->validate();

        if ($this->editingContainer) {
            $this->editingContainer->update($data);
        } else {
            Auth::user()->currentTeam->containers()->create($data);
        }

        $this->reset();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:location,bin'],
            'category' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:containers,id'],
        ];
    }
}
