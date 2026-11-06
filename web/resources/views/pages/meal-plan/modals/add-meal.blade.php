<flux:modal name="add-meal" class="md:w-96">
    <form wire:submit="addMeal" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Add a meal') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Pick a recipe, or type anything, like "Leftovers" or "Pizza night".') }}</flux:text>
        </div>

        <flux:autocomplete wire:model="meal" :label="__('Meal')" :placeholder="__('Search recipes or type a meal')" autofocus>
            @foreach ($this->recipeNames as $name)
                <flux:autocomplete.item>{{ $name }}</flux:autocomplete.item>
            @endforeach
        </flux:autocomplete>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
        </div>
    </form>
</flux:modal>
