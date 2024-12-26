<?php

namespace App\Livewire\Pages\Collections;

use App\Livewire\Concerns\WithDataTable;
use App\Models\LegoPiece;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Lego extends Component
{
    use WithDataTable;
    use WithPagination;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.collections.lego');
    }

    #[Computed]
    public function pieces(): LengthAwarePaginator
    {
        return LegoPiece::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }
}
