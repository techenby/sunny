<div class="p-2">
    @if ($temp !== null)
        <x-kiosk.weather :$location :$temp :$high :$low :$icon :$description />
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
