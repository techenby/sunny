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
 * @property Collection $things
 */
class Things extends Component
{
    use WithDataTable;
    use WithPagination;

    #[Validate('required|min:3')]
    public $name = '';

    #[Validate('nullable|exists:App\Models\Bin,id')]
    public $bin_id = '';

    #[Validate('nullable|exists:App\Models\Location,id')]
    public $location_id = '';

    public $editingThing = null;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.inventory.things', [
            'locations' => Location::all()->sortBy('name')->pluck('name', 'id'),
            'bins' => $this->bins->sortBy('name')->pluck('name', 'id'),
        ]);
    }

    public function updating($property, $value): void
    {
        if ($property === 'bin_id') {
            $this->location_id = $this->bins->firstWhere('id', $value)->location_id;
        }
    }

    #[Computed]
    public function bins(): Collection
    {
        return Bin::all();
    }

    #[Computed]
    public function things(): LengthAwarePaginator
    {
        return Thing::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function delete(int $id): void
    {
        $this->things->firstWhere('id', $id)->delete();
        unset($this->things);
    }

    public function edit(int $id): void
    {
        $this->editingThing = $this->things->firstWhere('id', $id);
        $this->name = $this->editingThing->name;
        $this->bin_id = $this->editingThing->location_id;
        $this->location_id = $this->editingThing->location_id;

        $this->modal('thing-form')->show();
    }

    public function save(): void
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
}
