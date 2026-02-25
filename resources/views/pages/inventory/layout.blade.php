<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Inventory') }}">
            <flux:navlist.item :href="route('inventory.index')" wire:navigate>{{ __('Overview') }}</flux:navlist.item>
            <flux:navlist.item :href="route('inventory.containers')" wire:navigate>{{ __('Containers') }}</flux:navlist.item>
            <flux:navlist.item :href="route('inventory.items')" wire:navigate>{{ __('Items') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </div>
</div>
