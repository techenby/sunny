<?php

namespace App\Livewire\Pages\Inventory;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Thing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property Collection $bins
 */
class Bins extends Component
{
    use WithDataTable;
    use WithPagination;

    #[Validate('required|min:3')]
    public $name = '';

    #[Validate('nullable|exists:App\Models\Location,id')]
    public $location_id = '';

    #[Validate('nullable|min:3')]
    public $type = '';

    public $editingBin = null;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.inventory.bins', [
            'locations' => Location::all()->sortBy('name')->pluck('name', 'id'),
        ]);
    }

    #[Computed]
    public function bins(): LengthAwarePaginator
    {
        return Bin::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function delete(int $id): void
    {
        $bin = Bin::find($id);

        Thing::where('bin_id', $bin->id)->update(['bin_id' => null]);

        $bin->delete();
        unset($this->bins);
    }

    public function edit(int $id): void
    {
        $this->editingBin = Bin::find($id);
        $this->name = $this->editingBin->name;
        $this->location_id = $this->editingBin->location_id;
        $this->type = $this->editingBin->type;

        $this->modal('bin-form')->show();
    }

    public function save(): void
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
}
