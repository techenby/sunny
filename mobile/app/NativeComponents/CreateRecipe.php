<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRecipeForm;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class CreateRecipe extends NativeComponent
{
    use ChecksSunnySync;
    use ManagesRecipeForm;

    public function mount(): void
    {
        $this->initializeTeam();
    }

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('recipes', $this->recipePayload());
    }

    public function render(): View
    {
        return view('native.create-recipe');
    }
}
