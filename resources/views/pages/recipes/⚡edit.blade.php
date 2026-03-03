<?php

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
        <flux:card>
            <div class="grid gap-6 md:grid-cols-2">
                <flux:input
                    wire:model="form.name"
                    :label="__('Name')"
                    type="text"
                    required
                />

                <flux:input
                    wire:model="form.source"
                    :label="__('Source (URL or text)')"
                    type="text"
                />
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Time & Servings') }}</flux:heading>

            <div class="grid gap-6 md:grid-cols-4">
                <flux:input
                    wire:model="form.servings"
                    :label="__('Servings')"
                    type="text"
                    placeholder="e.g., 4 people"
                />

                <flux:input
                    wire:model="form.prep_time"
                    :label="__('Prep Time')"
                    type="text"
                    placeholder="e.g., 30 min"
                />

                <flux:input
                    wire:model="form.cook_time"
                    :label="__('Cook Time')"
                    type="text"
                    placeholder="e.g., 1 hour"
                />

                <flux:input
                    wire:model="form.total_time"
                    :label="__('Total Time')"
                    type="text"
                    placeholder="e.g., 1 hour 30 min"
                />
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Description') }}</flux:heading>

            <flux:textarea
                wire:model="form.description"
                :placeholder="__('Brief description of the recipe...')"
                rows="3"
            />
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Ingredients') }}</flux:heading>

            <flux:textarea
                wire:model="form.ingredients"
                :placeholder="__('List ingredients, one per line...')"
                rows="8"
            />
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Instructions') }}</flux:heading>

            <flux:textarea
                wire:model="form.instructions"
                :placeholder="__('Step-by-step instructions...')"
                rows="10"
            />
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Notes') }}</flux:heading>

            <flux:textarea
                wire:model="form.notes"
                :placeholder="__('Additional notes, tips, or variations...')"
                rows="4"
            />
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Nutrition') }}</flux:heading>

            <flux:textarea
                wire:model="form.nutrition"
                :placeholder="__('Nutritional information...')"
                rows="4"
            />
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('recipes.show', $recipe)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Update Recipe') }}</flux:button>
        </div>
    </form>
</div>
