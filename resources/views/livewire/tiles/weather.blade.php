<?php

use Spatie\Dashboard\Models\Tile;

use function Livewire\Volt\{state, with};

state(['position']);

with(function () {
    return [
        'weather' => Tile::firstWhere('name', 'weather')->data ?? [],
    ];
});

?>

<x-dashboard-tile :position="$position" refresh-interval="60">
    <div class="flex items-end justify-between">
        <h1>Plainfield</h1>
        <x-weather-icon class="w-6 h-6" :id="data_get($weather, 'current.weather.0.id', 800)" />
    </div>
    @if (isset($weather['current']))
    <div class="flex items-end justify-between">
        <div class="text-2xl">{{ round($weather['current']['temp']) }}°</div>
        <div class="space-x-2">
            <span class="text-dimmed">{{ round($weather['daily'][0]['temp']['min']) }}</span>
            <span>{{ round($weather['daily'][0]['temp']['max']) }}°</span>
        </div>
    </div>
    @foreach ($weather['daily'] as $index => $day)
    @if ($index == 0)
        @continue
    @endif
    <div class="flex items-end justify-between py-1">
        <div class="flex space-x-4">
            <span class="w-8">{{ Carbon\Carbon::parse($day['dt'])->shortEnglishDayOfWeek }}</span>
            <x-weather-icon class="w-6 h-6" :id="$day['weather'][0]['id']" />
        </div>
        <div class="space-x-2">
            <span class="text-dimmed">{{ round($day['temp']['min']) }}</span>
            <span>{{ round($day['temp']['max']) }}°</span>
        </div>
    </div>
    @endforeach
    @endif
</x-dashboard-tile>
