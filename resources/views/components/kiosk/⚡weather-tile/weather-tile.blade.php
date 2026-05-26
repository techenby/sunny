<div class="p-2">
    @if ($temp !== null)
        <div >
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
    @else
        <flux:skeleton.group animate="shimmer">
            <div class="flex justify-between">
                <div>
                    <flux:skeleton.line class="w-16 mb-1" />
                    <flux:skeleton.line size="lg" class="w-12" />
                </div>
                <div class="flex flex-col items-end gap-1">
                    <flux:skeleton class="size-4 rounded" />
                    <flux:skeleton.line class="w-10" />
                    <flux:skeleton.line class="w-10" />
                </div>
            </div>
        </flux:skeleton.group>
    @endif
</div>
