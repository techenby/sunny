<?php

use App\Actions\ImportRecipeFromUrl;
use App\Livewire\Forms\Recipes\RecipeForm;
use App\Models\Recipe;
use Livewire\Component;

new class extends Component {
    public Recipe $recipe;

    public RecipeForm $form;

    public function mount(): void
    {
        $this->form->load($this->recipe);
    }

    public function import(): void
    {
        $this->validate([
            'form.source' => ['required', 'url'],
        ]);

        try {
            $data = (new ImportRecipeFromUrl)->handle($this->form->source);

            $this->form->fill(array_filter($data));

            Flux::toast(variant: 'success', heading: 'Recipe imported!', text: 'Review the fields below and click "Update Recipe" to save.');
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', heading: 'Import failed', text: $e->getMessage());
        }
    }

    public function save(): void
    {
        $this->form->save();

        $this->redirect(route('recipes.show', $this->recipe), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <flux:button :href="route('recipes.show', $recipe)" icon="arrow-left" variant="ghost" wire:navigate />
        <flux:heading size="xl">{{ __('Edit Recipe') }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        @include('pages.recipes.form')

        <div class="flex justify-end gap-2">
            <flux:button :href="route('recipes.show', $recipe)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Update Recipe') }}</flux:button>
        </div>
    </form>
</div>
