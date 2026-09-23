<?php

namespace App\NativeComponents;

use App\Concerns\ManagesRecipeForm;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class CreateRecipe extends NativeComponent
{
    use ManagesRecipeForm;

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        // The cookbook is still the hardcoded placeholder list in
        // Recipes::all(), so there is nowhere to write to yet — return to the
        // list once the form is valid, until the sunnyhome.app API is wired up.
        $this->back();
    }

    public function render(): View
    {
        return view('native.create-recipe');
    }
}
