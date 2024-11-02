<?php

use App\Http\Integrations\OpenWeather\OpenWeather;
use App\Http\Integrations\OpenWeather\Requests\OneCall;

use function Livewire\Volt\mount;
use function Livewire\Volt\state;

state(['position', 'weather']);

mount(function () {
    $openWeather = new OpenWeather();
    $oneCall = new OneCall();

    $this->weather = $openWeather->send($oneCall)->json();
});

?>

<x-dashboard-tile :position="$position">
    <h1>Plainfield</h1>
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
</x-dashboard-tile>
