<?php

namespace App\Livewire\Pages\Collections;

use Livewire\Attributes\Layout;
use Livewire\Component;

class Lego extends Component
{
    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.collections.lego');
    }
}
