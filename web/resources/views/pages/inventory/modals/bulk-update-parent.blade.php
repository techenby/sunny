@teleport('body')
<flux:modal name="bulk-update-parent" class="md:w-96">
    <form wire:submit="updateParent" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Change Parent') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Move :count item(s) to a new parent.', ['count' => count($selected)]) }}</flux:text>
        </div>
        <flux:select wire:model="bulkParentId" :label="__('Parent')" :placeholder="__('None (top level)')" variant="listbox" clearable>
            @foreach ($this->bulkParentOptions as $parentItem)
                <flux:select.option :value="$parentItem->id">{{ $parentItem->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Update') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
