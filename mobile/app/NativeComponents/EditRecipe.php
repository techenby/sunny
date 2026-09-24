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

        $this->error = 'Saving changes is not available yet. Please edit this on the Sunny website.';
    }

    public function render(): View
    {
        return view('native.edit-recipe');
    }
}
