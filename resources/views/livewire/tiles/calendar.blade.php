<?php

use Illuminate\Support\Carbon;
use Sabre\VObject\Reader;

use function Livewire\Volt\{computed, state};

state(['position' => '', 'links' => '', 'label' => '']);

$events = computed(function () {
    return collect($this->links)->flatMap(function ($link) {
        $vcalendar = Reader::read(fopen($link, "r"));

        return collect($vcalendar->VEVENT)
          ->map(function ($event) {
            return [
              "name" => (string) $event->SUMMARY,
              "start" => $start = Carbon::parse($event->DTSTART?->getDateTimes()[0]),
              "end" => $end = Carbon::parse($event->DTEND?->getDateTimes()[0]),
              'duration' => $start->shortAbsoluteDiffForHumans($end),
              'formatted' => $start->format('D, M jS g:i a'),
            ];
          })
          ->filter(fn($event) => $event["start"]->isFuture())
          ->sortBy('start');
    });
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
