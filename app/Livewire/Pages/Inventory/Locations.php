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

    public function store()
    {
        $this->validate();

        Location::create(['name' => $this->name]);

        $this->reset('name');
        unset($this->locations);
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
}
