<?php

namespace App\Livewire\Pages\Inventory;

use App\Models\Bin;
use App\Models\Location;
use App\Models\Thing;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Things extends Component
{
    use WithPagination;

    #[Validate('required|min:3')]
    public $name = '';

    #[Validate('nullable|exists:App\Models\Bin,id')]
    public $bin_id = '';

    #[Validate('nullable|exists:App\Models\Location,id')]
    public $location_id = '';

    public $editingThing = null;

    public $perPage = 10;
    public $search = '';
    public $sortBy = '';
    public $sortDirection = 'desc';

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.inventory.things', [
            'locations' => Location::all()->sortBy('name')->pluck('name', 'id'),
            'bins' => $this->bins->sortBy('name')->pluck('name', 'id'),
        ]);
    }

    public function updating($property, $value)
    {
        if ($property === 'bin_id') {
            $this->location_id = $this->bins->firstWhere('id', $value)->location_id;
        }
    }

    #[Computed]
    public function bins()
    {
        return Bin::all();
    }

    #[Computed]
    public function things()
    {
        return Thing::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function delete($id)
    {
        $this->things->firstWhere('id', $id)->delete();
        unset($this->things);
    }

    public function edit($id)
    {
        $this->editingThing = $this->things->firstWhere('id', $id);
        $this->name = $this->editingThing->name;
        $this->bin_id = $this->editingThing->location_id;
        $this->location_id = $this->editingThing->location_id;

        $this->modal('thing-form')->show();
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->editingThing) {
            $this->editingThing->update($validated);
        } else {
            Thing::create($validated);
        }

        $this->reset(['name', 'bin_id', 'location_id']);
        unset($this->things);
        $this->modal('thing-form')->close();
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
