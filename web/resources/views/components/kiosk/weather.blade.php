@props(['location', 'temp', 'high', 'low', 'icon', 'description'])

<div>
    <div class="flex justify-between items-center">
        <flux:text>{{ $location }}</flux:text>
        <img src="https://openweathermap.org/img/wn/{{ $icon }}@2x.png" alt="{{ $description }}" class="size-8 -mr-1.5" />
    </div>
    <div class="flex justify-between">
        <flux:heading size="xl">{{ $temp }}°</flux:heading>
        <div>
            <flux:text variant="strong"><flux:icon.arrow-up class="inline" variant="micro"/> {{ $high }}°</flux:text>
            <flux:text variant="subtle"><flux:icon.arrow-down class="inline" variant="micro"/> {{ $low }}°</flux:text>
        </div>
    </div>
</div>
