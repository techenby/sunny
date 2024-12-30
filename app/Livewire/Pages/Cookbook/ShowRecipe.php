<?php

namespace App\Livewire\Pages\Cookbook;

use App\Models\Recipe;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ShowRecipe extends Component
{
    public Recipe $recipe;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.cookbook.show-recipe')
            ->title($this->recipe->name);
    }
}
