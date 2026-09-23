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
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        // The cookbook is still the hardcoded placeholder list in
        // Recipes::all(), so the edits cannot be written back yet — return to
        // the recipe once the form is valid, until the API is wired up.
        $this->back();
    }

    public function render(): View
    {
        return view('native.edit-recipe');
    }
}
