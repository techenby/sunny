<?php

namespace App\Livewire\Pages\Collections;

use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Forms\LegoBinForm;
use App\Models\LegoBin;
use App\Models\LegoColor;
use App\Models\LegoPart;
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

    public $colorKeyword = '';
    public $partKeyword = '';

    public $filter = [
        'part' => '',
        'color' => '',
    ];

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.collections.lego');
    }

    #[Computed]
    public function colors(): Collection
    {
        return LegoColor::search($this->colorKeyword)->take(10)->get();
    }

    #[Computed]
    public function filterColors(): Collection
    {
        return LegoColor::select('id', 'name', 'hex')
            ->join('lego_bin_color', 'lego_bin_color.color_id', '=', 'lego_colors.id')
            ->groupBy('lego_colors.id')
            ->get();
    }

    #[Computed]
    public function filterParts(): Collection
    {
        return LegoPart::select('id', 'name', 'image')
            ->join('lego_bin_part', 'lego_bin_part.part_id', '=', 'lego_parts.id')
            ->groupBy('lego_parts.id')
            ->get();
    }

    #[Computed]
    public function parts(): Collection
    {
        return LegoPart::search($this->partKeyword)->take(10)->get();
    }

    #[Computed]
    public function bins(): LengthAwarePaginator
    {
        return LegoBin::query()
            ->with(['colors', 'parts'])
            ->when($this->sortBy, fn ($query) => $query->orderBy($this->sortBy, $this->sortDirection))
            ->when($this->search, fn ($query) => $query->where('type', 'like', '%' . $this->search . '%'))
            ->when($this->filter['part'] !== '' || $this->filter['color'], function ($query) {
                $query->join('lego_bin_part', 'lego_bin_part.bin_id', '=', 'lego_bins.id')
                    ->join('lego_bin_color', 'lego_bin_color.bin_id', '=', 'lego_bins.id')
                    ->when($this->filter['part'] !== '', fn ($query) => $query->where('part_id', $this->filter['part']))
                    ->when($this->filter['color'] !== '', fn ($query) => $query->where('color_id', $this->filter['color']))
                    ->groupBy('lego_bins.id');
            })
            ->paginate($this->perPage);
    }

    public function delete(int $id): void
    {
        $bin = LegoBin::find($id);

        $bin->parts()->detach();
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
