<?php

use App\Actions\ImportRecipeFromUrl;
use App\Livewire\Forms\Recipes\RecipeForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public RecipeForm $form;

    public string $importUrl = '';

    public function import(): void
    {
        $this->validate([
            'importUrl' => ['required', 'url'],
        ]);

        try {
            $data = (new ImportRecipeFromUrl)->handle($this->importUrl);

            $this->form->fill(array_filter($data, fn ($value) => $value !== null && $value !== ''));

            Flux::toast(variant: 'success', heading: 'Recipe imported!', text: 'Review the fields below and click "Create Recipe" to save.');
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', heading: 'Import failed', text: $e->getMessage());
        }
    }

    public function save(): void
    {
        $this->form->save();

        $this->redirect(route('recipes.index'), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <flux:button :href="route('recipes.index')" icon="arrow-left" variant="ghost" wire:navigate />
        <flux:heading size="xl">{{ __('Add Recipe') }}</flux:heading>
    </div>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Import from URL') }}</flux:heading>

        <form wire:submit="import" class="flex items-end gap-2">
            <div class="flex-1">
                <flux:input
                    wire:model="importUrl"
                    :label="__('Recipe URL')"
                    type="url"
                    placeholder="https://example.com/recipe"
                />
            </div>

            <flux:button type="submit" variant="filled" icon="arrow-down-tray" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="import">{{ __('Import') }}</span>
                <span wire:loading wire:target="import">{{ __('Importing...') }}</span>
            </flux:button>
        </form>
    </flux:card>

    <form wire:submit="save" class="space-y-6">
        @include('pages.recipes.form')

        <div class="flex justify-end gap-2">
            <flux:button :href="route('recipes.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Create Recipe') }}</flux:button>
        </div>
    </form>
</div>
