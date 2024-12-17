<?php

namespace App\Livewire\Pages\Cookbook;

use App\Livewire\Forms\RecipeForm;
use App\Models\Recipe;
use Livewire\Attributes\Layout;
use Livewire\Component;

class EditRecipe extends Component
{
    public Recipe $recipe;
    public RecipeForm $form;

    public function mount()
    {
        $this->form->set($this->recipe);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.cookbook.edit-recipe');
    }
}
