<?php

namespace App\Livewire\Collections\Lego;

use App\Models\LegoGroup;
use App\Models\LegoPart;
use Livewire\Attributes\Layout;
use Livewire\Component;

class PartList extends Component
{
    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.collections.lego.part-list')
            ->with([
                'groups' => LegoGroup::all(),
                'parts' => LegoPart::all(),
            ]);
    }
}
