<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use App\Actions\Recipes\ImportRecipeFromUrl;
use App\Livewire\Forms\Recipes\RecipeForm;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    public RecipeForm $form;

    public function import(): void
    {
        $this->validate([
            'form.source' => ['required', 'url:http,https', 'max:500'],
        ]);

        if (! RateLimiter::attempt('recipe-import:' . Auth::id(), 30, fn (): bool => true)) {
            Flux::toast(variant: 'danger', heading: 'Import failed', text: __('That’s a lot of imports in a row. Wait a minute and try again.'));

            return;
        }

        try {
            $data = (new ImportRecipeFromUrl)->handle($this->form->source);

            $this->form->fill(array_filter($data));

            Flux::toast(variant: 'success', heading: 'Recipe imported!', text: 'Review the fields below and click "Create Recipe" to save.');
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', heading: 'Import failed', text: $e->getMessage());
        }
    }

    public function removePhoto(): void
    {
        if ($this->form->photo) {
            $this->form->photo->delete();
            $this->form->photo = null;
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

    <form wire:submit="save" class="space-y-6">
        @include('pages.recipes.form')

        <div class="flex justify-end gap-2">
            <flux:button :href="route('recipes.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Create Recipe') }}</flux:button>
        </div>
    </form>
</div>
