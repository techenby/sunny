<?php

namespace App\Livewire\Pages\Inventory;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Thing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property LengthAwarePaginator $locations
 */
class Locations extends Component
{
    use WithDataTable;
    use WithPagination;

    #[Validate('required|min:3')]
    public $name = '';
    public $editingLocation = null;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.inventory.locations');
    }

    #[Computed]
    public function locations(): LengthAwarePaginator
    {
        return Location::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function delete($id)
    {
        $location = Location::find($id);

        Bin::where('location_id', $location->id)->update(['location_id' => null]);
        Thing::where('location_id', $location->id)->update(['location_id' => null]);

        $location->delete();
        unset($this->locations);
    }

    public function edit($id)
    {
        $this->editingLocation = Location::find($id);
        $this->name = $this->editingLocation->name;

        $this->modal('location-form')->show();
    }

    public function save()
    {
        $this->validate();

        if ($this->editingLocation) {
            $this->editingLocation->update(['name' => $this->name]);
        } else {
            Location::create(['name' => $this->name]);
        }

        $this->reset('name');
        unset($this->locations);
        $this->modal('location-form')->close();
    }
}
