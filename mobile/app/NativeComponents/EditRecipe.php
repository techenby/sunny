<?php

namespace App\NativeComponents;

use App\Concerns\ManagesRecipeForm;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class EditRecipe extends NativeComponent
{
    use ManagesRecipeForm;

    public function mount(): void
    {
        if ($this->recipe !== null) {
            $this->fillFromRecipe($this->recipe);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function recipe(): ?array
    {
        return Recipes::find((int) $this->param('id'));
    }

    public function save(): void
    {
        if ($this->recipe === null) {
            $this->error = 'This record could not be found.';

            return;
        }

        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('recipes', $this->recipePayload($this->recipe), $this->recipe['id']);
    }

    public function render(): View
    {
        return view('native.edit-recipe');
    }
}
