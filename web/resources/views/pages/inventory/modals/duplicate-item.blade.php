@teleport('body')
<flux:modal name="duplicate-item" class="md:w-96">
    <form wire:submit="duplicate" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Make copies') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Each copy goes in the same place as the original.') }}</flux:text>
        </div>
        <flux:input type="number" min="1" max="25" wire:model="duplicateCount" :label="__('How many copies?')" />
        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Make copies') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
