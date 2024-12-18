<?php

namespace App\Livewire\Pages\Cookbook;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Recipe;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Recipes extends Component
{
    use WithDataTable;
    use WithPagination;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.cookbook.recipes');
    }

    #[Computed]
    public function recipes(): LengthAwarePaginator
    {
        return Recipe::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function delete($id)
    {
        Recipe::find($id)->delete();
        unset($this->recipes);
    }
}
