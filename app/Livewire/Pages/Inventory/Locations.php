<?php

namespace App\Livewire\Pages\Inventory;

use App\Models\Location;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Locations extends Component
{
    use WithPagination;

    #[Validate('required|min:3')]
    public $name = '';
    public $editingLocation = null;

    public $sortBy = '';
    public $sortDirection = 'desc';

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.inventory.locations');
    }

    #[Computed]
    public function locations()
    {
        return Location::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->paginate(10);
    }

    public function edit($id)
    {
        $this->editingLocation = $this->locations->firstWhere('id', $id);
        $this->name = $this->editingLocation->name;

        $this->modal('edit-location')->show();
    }

    public function store()
    {
        $this->validate();

        Location::create(['name' => $this->name]);

        $this->reset('name');
        unset($this->locations);
        $this->modal('create-location')->close();
    }

    public function sort($column)
    {
        if ($this->sortBy === $column && $this->sortDirection === 'asc') {
            $this->reset('sortBy', 'sortDirection');
        } elseif ($this->sortBy === $column) {
            $this->sortDirection = 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }
    }

    public function update()
    {
        $this->validate();

        $this->editingLocation->update(['name' => $this->name]);

        $this->reset('name');
        unset($this->locations);
        $this->modal('edit-location')->close();
    }
}
