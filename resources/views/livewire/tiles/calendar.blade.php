<?php

use Illuminate\Support\Carbon;
use Sabre\VObject\Reader;
use App\Models\Tile;

use function Livewire\Volt\{computed, state};

state(['position' => '', 'name' => '', 'label' => '', 'timezone' => 'America/Chicago']);

$events = computed(function () {
    return Tile::where('type', 'calendar')
        ->when(
            is_array($this->name),
            fn ($query) => $query->whereIn('name', $this->name),
            fn ($query) => $query->where('name', $this->name),
        )->get()->pluck('data')->flatten(1)
        ->sortBy('start')
        ->filter(fn ($event) => ! $event['past'])
        ->map(function ($event) {
            $start = Carbon::parse($event['start'], $this->timezone);
            $end = Carbon::parse($event['end']['date'], $this->timezone);
            return [
                'name' => $event['name'],
                'formatted' => $start->format($event['allDay'] ? 'D, M jS' : 'D, M jS g:i a'),
                'duration' => $start->shortAbsoluteDiffForHumans($end),
            ];
        });
});
?>

<x-dashboard-tile :position="$position" refresh-interval="60">
    <h1 class="uppercase font-bold">{{ $label }}</h1>
    <div class="mt-2 self-center divide-y-2 divide-canvas overflow-y-scroll h-full -mx-3 px-3">
        @foreach ($this->events as $event)
        <div class="py-2">
            <p class="font-bold">{{ $event['name'] }}</p>
            <p class="text-sm text-dimmed">{{ $event['formatted'] ?? '-' }} ({{ $event['duration'] ?? '-' }})</p>
        </div>
        @endforeach
    </div>
</x-dashboard-tile>
