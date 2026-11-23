<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRecipeForm;
use App\Layouts\HidesTabBar;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;

class EditRecipe extends Component implements HidesTabBar
{
    use ChecksSunnySync;
    use ManagesRecipeForm;

    public bool $justImported = false;

    public bool $justRemixed = false;

    public function mount(): void
    {
        if ($this->recipe !== null) {
            $this->fillFromRecipe($this->recipe);
            $this->justRemixed = (bool) $this->data('remixed', false);
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

    /**
     * @return array{id: int, name: string}|null
     */
    #[Computed]
    public function remixOf(): ?array
    {
        $parentId = $this->recipe['parent_id'] ?? null;

        return $this->justRemixed && $parentId !== null ? Recipes::find($parentId) : null;
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

        $this->saveRecord('recipes', $this->recipePayload(), $this->recipe['id'], openAfterSaving: $this->justRemixed);
    }

    public function render(): View
    {
        return view('native.edit-recipe');
    }
}
