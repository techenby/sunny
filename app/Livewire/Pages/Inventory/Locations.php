<?php

namespace App\Livewire\Pages\Inventory;

use App\Models\Location;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Locations extends Component
{
    use WithPagination;

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

    public function sort($column) {
        if ($this->sortBy === $column && $this->sortDirection === 'asc') {
            $this->reset('sortBy', 'sortDirection');
        } else if ($this->sortBy === $column) {
            $this->sortDirection = 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }
    }
}
