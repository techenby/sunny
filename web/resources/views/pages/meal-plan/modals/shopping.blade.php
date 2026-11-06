<flux:modal name="shopping" class="w-full md:max-w-xl">
    <form wire:submit="addToShoppingList" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Add ingredients to a shopping list') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Untick anything you already have.') }}</flux:text>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <flux:input type="date" wire:model.live="shoppingFrom" :label="__('From')" />
            <flux:input type="date" wire:model.live="shoppingThrough" :label="__('Through')" />
        </div>

        @if ($this->ingredientGroups->isEmpty())
            <flux:text>{{ __('No planned recipes with ingredients between these dates.') }}</flux:text>
        @else
            <div class="max-h-96 space-y-4 overflow-y-auto">
                @foreach ($this->ingredientGroups as $group)
                    <flux:checkbox.group wire:key="group-{{ $group['recipe']->id }}" wire:model="selectedLines" :label="$group['count'] > 1 ? __(':name (planned :count times)', ['name' => $group['recipe']->name, 'count' => $group['count']]) : $group['recipe']->name">
                        @foreach ($group['lines'] as $index => $line)
                            <flux:checkbox wire:key="line-{{ $group['recipe']->id }}-{{ $index }}" :value="$group['recipe']->id . ':' . $index" :label="$line" />
                        @endforeach
                    </flux:checkbox.group>
                @endforeach
            </div>
            <flux:error name="selectedLines" />
        @endif

        @if ($this->shoppingLists->isEmpty())
            <flux:text>
                {{ __('You have no shopping lists yet.') }}
                <flux:link :href="route('lists.index')" wire:navigate>{{ __('Create one in Lists.') }}</flux:link>
            </flux:text>
        @else
            <flux:select wire:model="shoppingListId" :label="__('Shopping list')" variant="listbox">
                @foreach ($this->shoppingLists as $list)
                    <flux:select.option :value="$list->id">{{ $list->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" :disabled="$this->ingredientGroups->isEmpty() || $this->shoppingLists->isEmpty()">
                {{ __('Add to list') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
