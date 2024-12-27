<?php

namespace App\Livewire\Pages\Collections;

use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Forms\LegoBinForm;
use App\Models\LegoBin;
use App\Models\LegoColor;
use App\Models\LegoPiece;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Lego extends Component
{
    use WithDataTable;
    use WithPagination;

    public LegoBinForm $form;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.collections.lego');
    }

    #[Computed]
    public function colors(): Collection
    {
        return LegoColor::select('id', 'name', 'hex')->get();
    }

    #[Computed]
    public function pieces(): Collection
    {
        return LegoPiece::select('id', 'name', 'image')->get();
    }

    #[Computed]
    public function bins(): LengthAwarePaginator
    {
        return LegoBin::query()
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('type', 'like', '%' . $this->search . '%'))
            ->paginate($this->perPage);
    }

    public function delete(int $id): void
    {
        $bin = LegoBin::find($id);

        $bin->pieces()->detach();
        $bin->colors()->detach();

        $bin->delete();
    }

    public function edit(int $id): void
    {
        $this->form->set(LegoBin::find($id));
        $this->modal('bin-form')->show();
    }

    public function save(): void
    {
        if (isset($this->form->bin)) {
            $this->form->update();
        } else {
            $this->form->store();
        }

        unset($this->bins);
        $this->modal('bin-form')->close();
    }
}
