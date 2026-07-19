@teleport('body')
<flux:modal name="duplicate-item" class="md:w-96">
    <form wire:submit="duplicate" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Duplicate Item') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Create one or more copies of this item.') }}</flux:text>
        </div>
        <flux:input type="number" min="1" max="25" wire:model="duplicateCount" :label="__('Number of copies')" />
        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Duplicate') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
