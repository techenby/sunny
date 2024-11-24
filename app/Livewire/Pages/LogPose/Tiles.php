<?php

namespace App\Livewire\Pages\LogPose;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Tile;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Tiles extends Component
{
    use WithDataTable;
    use WithPagination;

    #[Validate('required|min:3')]
    public $name = '';

    #[Validate('required|min:3')]
    public $type = '';

    #[Validate('nullable')]
    public $data = null;

    #[Validate('nullable')]
    public $settings = [];

    public $editingTile = null;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.log-pose.tiles');
    }

    #[Computed]
    public function tiles(): LengthAwarePaginator
    {
        return Tile::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function delete(int $id): void
    {
        Tile::find($id)->delete();
        unset($this->tiles);
    }

    public function edit(int $id): void
    {
        $this->editingTile = Tile::find($id);
        $this->name = $this->editingTile->name;
        $this->type = $this->editingTile->type;
        $this->settings = $this->editingTile->settings;
        $this->data = $this->editingTile->getRawOriginal('data');

        $this->modal('tile-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->editingTile) {
            $validated['data'] = json_decode($validated['data'], true);
            $this->editingTile->update($validated);
        } else {
            Tile::create($validated);
        }

        $this->reset(['name', 'type', 'data', 'settings']);
        unset($this->tiles);
        $this->modal('tile-form')->close();
    }

    public function updatedType()
    {
        if ($this->type === 'calendar') {
            $this->settings = ['color' => '#', 'links' => ['']];
        }
    }
}
