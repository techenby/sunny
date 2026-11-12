@teleport('body')
<flux:modal name="bulk-update-parent" class="md:w-96">
    <form wire:submit="updateParent" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ trans_choice('Move :count thing|Move :count things', count($selected)) }}</flux:heading>
            <flux:text class="mt-2">{{ __('Choose the place or storage to put them in.') }}</flux:text>
        </div>
        <flux:select wire:model="bulkParentId" :label="__('Where do they go?')" :placeholder="__('Not in a place yet')" variant="listbox" searchable clearable>
            @foreach ($this->bulkParentOptions as $parentItem)
                <flux:select.option :value="$parentItem->id">{{ $parentItem->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Move') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
