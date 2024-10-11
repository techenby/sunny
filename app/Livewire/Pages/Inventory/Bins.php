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

class Bins extends Component
{
    use WithPagination;

    #[Validate('required|min:3')]
    public $name = '';

    #[Validate('nullable|exists:App\Models\Location,id')]
    public $location_id = null;

    #[Validate('nullable|min:3')]
    public $type = '';

    public $editingBin = null;

    public $perPage = 10;
    public $search = '';
    public $sortBy = '';
    public $sortDirection = 'desc';

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.inventory.bins', [
            'locations' => Location::all()->sortBy('name')->pluck('name', 'id'),
        ]);
    }

    #[Computed]
    public function bins()
    {
        return Bin::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function delete($id)
    {
        $bin = $this->bins->firstWhere('id', $id);

        Thing::where('bin_id', $bin->id)->update(['bin_id' => null]);

        $bin->delete();
        unset($this->bins);
    }

    public function edit($id)
    {
        $this->editingBin = $this->bins->firstWhere('id', $id);
        $this->name = $this->editingBin->name;
        $this->location_id = $this->editingBin->location_id;
        $this->type = $this->editingBin->type;

        $this->modal('bin-form')->show();
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->editingBin) {
            $this->editingBin->update($validated);
        } else {
            Bin::create($validated);
        }

        $this->reset(['name', 'location_id', 'type']);
        unset($this->bins);
        $this->modal('bin-form')->close();
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
