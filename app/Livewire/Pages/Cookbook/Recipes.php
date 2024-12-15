<?php

namespace App\Livewire\Pages\Cookbook;

use Livewire\Attributes\Layout;
use Livewire\Component;

class Recipes extends Component
{
    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.cookbook.recipes');
    }
}
