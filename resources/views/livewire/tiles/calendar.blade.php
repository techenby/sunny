<?php

use Illuminate\Support\Carbon;
use Sabre\VObject\Reader;
use App\Models\Tile;

use function Livewire\Volt\{computed, state};

state(['position' => '', 'name' => '', 'label' => '']);

$events = computed(function () {
    return Tile::firstWhere('name', $this->name)->data ?? [];
});
?>

<x-dashboard-tile :position="$position" refresh-interval="60">
    <h1 class="uppercase font-bold">{{ $label }}</h1>
    <div class="mt-2 self-center divide-y-2 divide-canvas overflow-y-scroll h-full -mx-3 px-3">
        @foreach ($this->events as $event)
        <div class="py-2">
            <p class="font-bold">{{ $event['name'] }}</p>
            <p class="text-sm text-dimmed">{{ $event['formatted'] }} ({{ $event['duration'] }})</p>
        </div>
        @endforeach
    </div>
</x-dashboard-tile>
