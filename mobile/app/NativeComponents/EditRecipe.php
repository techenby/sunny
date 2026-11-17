<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRecipeForm;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;

class EditRecipe extends Component
{
    use ChecksSunnySync;
    use ManagesRecipeForm;

    public bool $justImported = false;

    public function mount(): void
    {
        if ($this->recipe !== null) {
            $this->fillFromRecipe($this->recipe);
        }

        $this->justImported = (bool) $this->data('imported', false);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function recipe(): ?array
    {
        return Recipes::find((int) $this->param('id'));
    }

    /**
     * @return array{url: string, host: string, path: string, initial: string, state: string, message: string}|null
     */
    #[Computed]
    public function importCard(): ?array
    {
        $link = $this->justImported ? $this->sourceLink() : null;

        return $link === null ? null : [...$link, 'state' => 'imported', 'message' => ''];
    }

    public function dismissImported(): void
    {
        $this->justImported = false;
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

        $this->saveRecord('recipes', $this->recipePayload(), $this->recipe['id']);
    }

    public function render(): View
    {
        return view('native.edit-recipe');
    }
}
