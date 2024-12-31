<?php

namespace App\Livewire\Pages\Cookbook;

use App\Livewire\Forms\RecipeForm;
use App\Models\Recipe;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EditRecipe extends Component
{
    use WithFileUploads;

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

    #[Computed]
    public function previewUrl(): ?string
    {
        if ($this->form->image) {
            $url = $this->form->image->temporaryUrl();
        } elseif ($image = $this->recipe->getFirstMedia('thumb')) {
            $url = $image->getUrl();
        }

        return $url ?? null;
    }

    public function clear()
    {
        $this->form->reset(['image']);
    }

    public function save()
    {
        $this->form->update();

        Flux::toast('Saved.');
    }
}
