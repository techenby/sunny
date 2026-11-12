@teleport('body')
<flux:modal name="move-to-place" class="md:w-96">
    <form wire:submit="move" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Move :name', ['name' => $item->name]) }}</flux:heading>
            <flux:text class="mt-2">{{ __('Choose what to put it in. It can go inside anything in your inventory.') }}</flux:text>
        </div>
        <flux:select wire:model="moveParentId" :label="__('Where does it go?')" :placeholder="$item->type === \App\Enums\ItemType::Location ? __('Home') : __('Not in a place yet')" variant="listbox" searchable clearable>
            @foreach ($this->moveOptions as $option)
                <flux:select.option :value="$option->id">{{ $option->parent ? __(':name (in :parent)', ['name' => $option->name, 'parent' => $option->parent->name]) : $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Move') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
