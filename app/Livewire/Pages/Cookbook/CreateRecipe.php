<?php

namespace App\Livewire\Pages\Cookbook;

use App\Livewire\Forms\RecipeForm;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateRecipe extends Component
{
    use WithFileUploads;

    public RecipeForm $form;

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.pages.cookbook.create-recipe');
    }

    #[Computed]
    public function previewUrl(): ?string
    {
        if ($this->form->image) {
            return $this->form->image->temporaryUrl();
        }

        return null;
    }

    public function clear()
    {
        $this->form->reset(['image']);
    }

    public function save()
    {
        $recipe = $this->form->create();

        Flux::toast('Saved.');

        $this->redirectRoute('cookbook.recipes.edit', ['recipe' => $recipe]);
    }
}
