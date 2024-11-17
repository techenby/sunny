<?php

use Spatie\Dashboard\Models\Tile;

use function Livewire\Volt\{computed, state};

state([
    'position' => null,
    'name' => '',
]);

$coworkers = computed(function () {
    return Tile::firstWhere('name', $this->name)->data ?? [];
});
?>

<x-dashboard-tile :position="$position" refresh-interval="10">
    <div class="grid grid-rows-auto-auto gap-2 h-full">
        <h1 class="uppercase font-bold">Coworkers</h1>

        <ul class="self-center divide-y-2 divide-canvas overflow-y-scroll h-full -mx-3 px-3">
            @foreach ($this->coworkers as $coworker)
                <li class="py-1">
                    <div class="my-2 flex items-center justify-between space-x-2">
                        <div>
                            <div class="font-bold">{{ $coworker['name'] }}</div>
                            <div class="text-sm text-dimmed">{{ $coworker['location'] }}</div>
                        </div>
                        <div>
                            {{ now()->timezone($coworker['timezone'])->format('g:i A') }}
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</x-dashboard-tile>
