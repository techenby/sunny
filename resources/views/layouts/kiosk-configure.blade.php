<x-layouts::app>
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Kiosk Configuration') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Preview your kiosk and manage its data sources') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex items-start max-md:flex-col">
        <div class="me-10 w-full pb-4 md:w-[120px]">
            <flux:navlist aria-label="{{ __('Configuration') }}">
                <flux:navlist.item :href="route('kiosk.configure.preview')" wire:navigate>{{ __('Preview') }}</flux:navlist.item>
                <flux:navlist.item :href="route('kiosk.configure.calendar')" wire:navigate>{{ __('Calendar') }}</flux:navlist.item>
                <flux:navlist.item :href="route('kiosk.configure.settings')" wire:navigate>{{ __('Settings') }}</flux:navlist.item>
            </flux:navlist>
        </div>

        <flux:separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            <div class="w-full max-w-4xl">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts::app>
