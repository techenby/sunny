<?php

use App\Http\Integrations\OpenWeather\OpenWeatherConnector;
use App\Http\Integrations\OpenWeather\Requests\OneCall;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public ?string $location = null;

    public ?float $temp = null;

    public ?float $high = null;

    public ?float $low = null;

    public ?string $description = null;

    public ?string $icon = null;

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        if (! ($team->address['lat'] ?? null) || ! ($team->address['long'] ?? null)) {
            return;
        }

        $weather = Cache::remember(
            "weather:{$team->id}",
            now()->addMinutes(30),
            fn () => (new OpenWeatherConnector)->send(
                new OneCall($team->address['lat'], $team->address['long'], 'minutely,hourly,alerts')
            )->json(),
        );

        $this->location = $team->address['city'] ?? null;
        $this->temp = round($weather['current']['temp']);
        $this->high = round($weather['daily'][0]['temp']['max']);
        $this->low = round($weather['daily'][0]['temp']['min']);
        $this->description = $weather['current']['weather'][0]['description'] ?? null;
        $this->icon = $weather['current']['weather'][0]['icon'] ?? null;
    }
};
