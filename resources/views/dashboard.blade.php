<x-layouts::app :title="__('Dashboard')">
    <flux:heading level="1" size="xl" class="mb-4">What would you like to see here?</flux:heading>
    <flux:button icon="light-bulb" icon:trailing="arrow-up-right" href="https://suggest.gg/techenby" target="_blank">
        {{ __('Make a Suggestion') }}
    </flux:button>
</x-layouts::app>
